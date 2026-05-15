#!/usr/bin/env python3
"""
重新采集"发现你身边的行善者"分类的文章
处理不同域名的图片
"""

import requests
import re
import psycopg2
from psycopg2.extras import RealDictCursor
import time

# 数据库配置
DB_CONFIG = {
    'host': 'postgres.orb.local',
    'port': 5432,
    'database': 'postgres',
    'user': 'postgres',
    'password': 'postgres',
}

def fetch_article_full_content(url):
    """抓取文章的完整HTML内容"""
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        response = requests.get(url, headers=headers, timeout=30, verify=False)
        
        if response.status_code != 200:
            return None, None
        
        html = response.text
        
        # 提取所有图片（来自 gongyila.com 域名的所有子域名）
        all_images = re.findall(r'src="(https?://[^"]*gongyila\.com[^"]+)"', html)
        
        # 过滤出文章内容区域的图片（排除小图标等）
        article_images = [img for img in all_images if '/Uploads/' in img or '/tenant/' in img]
        
        # 第一张图片作为封面
        cover_image = article_images[0] if article_images else ''
        
        # 提取标题
        title_match = re.search(r'<div class="tit"[^>]*>(.*?)</div>', html, re.DOTALL)
        title = re.sub(r'<[^>]+>', '', title_match.group(1)).strip() if title_match else ''
        
        # 提取时间
        time_match = re.search(r'<div class="time"[^>]*>(.*?)</div>', html, re.DOTALL)
        time_str = re.sub(r'<[^>]+>', '', time_match.group(1)).strip() if time_match else ''
        
        # 提取描述
        desc_match = re.search(r'<div class="desc"[^>]*>(.*?)</div>', html, re.DOTALL)
        desc = re.sub(r'<[^>]+>', '', desc_match.group(1)).strip() if desc_match else ''
        
        # 提取正文内容区域
        content_pattern = r'<div class="article-detail"[^>]*>(.*?)</div>\s*<div class="side-content"'
        content_match = re.search(content_pattern, html, re.DOTALL)
        
        content_html = ''
        if content_match:
            content_html = content_match.group(1)
        else:
            content_match = re.search(r'<div class="content"[^>]*>(.*?)</div>', html, re.DOTALL)
            if content_match:
                content_html = content_match.group(1)
        
        # 组合完整内容
        full_content = ''
        if cover_image:
            full_content += f'<img src="{cover_image}" class="cover-image">\n'
        if title:
            full_content += f'<h1>{title}</h1>\n'
        if time_str:
            full_content += f'<p class="time">{time_str}</p>\n'
        if desc:
            full_content += f'<p class="description">{desc}</p>\n'
        if content_html:
            full_content += f'<div class="article-content">{content_html}</div>'
        
        return full_content.strip() if full_content.strip() else None, cover_image
        
    except Exception as e:
        print(f"  ✗ 抓取失败: {e}")
        return None, None

def main():
    # 连接数据库
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        print("✓ 数据库连接成功\n")
    except Exception as e:
        print(f"数据库连接失败: {e}")
        return
    
    # 获取没有封面图片的文章
    cursor.execute("""
        SELECT id, title, source_url 
        FROM jianhui_org_articles 
        WHERE category_id = 34 AND (cover_image IS NULL OR cover_image = '')
        ORDER BY id
    """)
    articles = cursor.fetchall()
    
    print(f"找到 {len(articles)} 篇没有封面的文章\n")
    
    if len(articles) == 0:
        print("所有文章都有封面图片")
        return
    
    # 开始抓取
    success_count = 0
    fail_count = 0
    
    for index, article in enumerate(articles):
        current = index + 1
        print(f"[{current}/{len(articles)}] 处理: {article['title']}")
        
        content, cover_image = fetch_article_full_content(article['source_url'])
        
        if cover_image:
            # 更新数据库
            try:
                cursor.execute(
                    "UPDATE jianhui_org_articles SET cover_image = %s, content = COALESCE(content, %s), updated_at = NOW() WHERE id = %s",
                    (cover_image, content, article['id'])
                )
                conn.commit()
                print(f"  ✓ 更新封面: {cover_image[:60]}...")
                success_count += 1
            except Exception as e:
                print(f"  ✗ 更新失败: {e}")
                fail_count += 1
        else:
            print(f"  ✗ 未找到图片")
            fail_count += 1
        
        time.sleep(0.3)
    
    print(f"\n=== 处理完成 ===")
    print(f"成功: {success_count} 篇")
    print(f"失败: {fail_count} 篇")
    
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
