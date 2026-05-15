#!/usr/bin/env python3
"""
建辉文章内容抓取脚本
"""

import requests
import re
import psycopg2
from psycopg2.extras import RealDictCursor
import time
import json

# 数据库配置
DB_CONFIG = {
    'host': 'postgres.orb.local',
    'port': 5432,
    'database': 'postgres',
    'user': 'postgres',
    'password': 'postgres',
}

def fetch_article_content(url):
    """抓取文章内容"""
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        response = requests.get(url, headers=headers, timeout=30, verify=False)
        
        if response.status_code != 200:
            return None
        
        html = response.text
        
        # 提取标题
        title_match = re.search(r'<div class="tit"[^>]*>(.*?)</div>', html, re.DOTALL)
        title = re.sub(r'<[^>]+>', '', title_match.group(1)).strip() if title_match else ''
        
        # 提取时间
        time_match = re.search(r'<div class="time"[^>]*>(.*?)</div>', html, re.DOTALL)
        time_str = re.sub(r'<[^>]+>', '', time_match.group(1)).strip() if time_match else ''
        
        # 提取描述
        desc_match = re.search(r'<div class="desc"[^>]*>(.*?)</div>', html, re.DOTALL)
        desc = re.sub(r'<[^>]+>', '', desc_match.group(1)).strip() if desc_match else ''
        
        # 提取正文内容
        content_match = re.search(r'<div class="content"[^>]*>(.*?)</div>', html, re.DOTALL)
        content = ''
        if content_match:
            content_html = content_match.group(1)
            # 清理 HTML
            content_html = re.sub(r'<script[^>]*>.*?</script>', '', content_html, flags=re.DOTALL)
            content_html = re.sub(r'<style[^>]*>.*?</style>', '', content_html, flags=re.DOTALL)
            content_html = re.sub(r'<!--.*?-->', '', content_html, flags=re.DOTALL)
            
            # 保留基本标签
            allowed_tags = ['p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 'a', 'img', 'ul', 'ol', 'li', 'blockquote']
            # 简化处理：移除所有标签
            content = re.sub(r'<[^>]+>', ' ', content_html)
            content = re.sub(r'\s+', ' ', content).strip()
        
        # 组合完整内容
        full_content = ''
        if title:
            full_content += f'<h1>{title}</h1>\n\n'
        if time_str:
            full_content += f'<p class="time">{time_str}</p>\n\n'
        if desc:
            full_content += f'<p class="description">{desc}</p>\n\n'
        if content:
            full_content += f'<p>{content}</p>'
        
        return full_content.strip() if full_content.strip() else None
        
    except Exception as e:
        print(f"  ✗ 抓取失败: {e}")
        return None

def main():
    # 连接数据库
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        print("✓ 数据库连接成功\n")
    except Exception as e:
        print(f"数据库连接失败: {e}")
        return
    
    # 检查空内容的文章
    cursor.execute("SELECT COUNT(*) as count FROM jianhui_org_articles WHERE content IS NULL OR TRIM(content) = ''")
    result = cursor.fetchone()
    empty_count = result['count']
    print(f"空内容文章数量: {empty_count}\n")
    
    if empty_count == 0:
        print("所有文章都有内容，无需抓取")
        return
    
    # 获取所有空内容的文章
    cursor.execute("""
        SELECT id, title, source_url 
        FROM jianhui_org_articles 
        WHERE content IS NULL OR TRIM(content) = '' 
        ORDER BY id
    """)
    articles = cursor.fetchall()
    
    print(f"共找到 {len(articles)} 篇空内容文章\n")
    
    # 测试抓取第一篇文章
    test_article = articles[0]
    print(f"=== 测试抓取第一篇文章 ===")
    print(f"文章ID: {test_article['id']}")
    print(f"标题: {test_article['title']}")
    print(f"URL: {test_article['source_url']}\n")
    
    content = fetch_article_content(test_article['source_url'])
    if content:
        print(f"✓ 成功抓取内容，长度: {len(content)} 字符")
        print(f"内容预览:\n{content[:300]}...\n")
    else:
        print("✗ 抓取内容失败\n")
    
    # 询问是否继续抓取所有文章
    answer = input("是否继续抓取所有文章？(yes/no): ").strip().lower()
    if answer != 'yes':
        print("已取消抓取")
        return
    
    # 开始抓取所有文章
    print("\n=== 开始抓取所有文章 ===")
    success_count = 0
    fail_count = 0
    fail_list = []
    
    for index, article in enumerate(articles):
        current = index + 1
        print(f"[{current}/{empty_count}] 抓取: {article['title']}")
        
        content = fetch_article_content(article['source_url'])
        
        if content and content.strip():
            # 更新数据库
            try:
                cursor.execute(
                    "UPDATE jianhui_org_articles SET content = %s, updated_at = NOW() WHERE id = %s",
                    (content, article['id'])
                )
                conn.commit()
                print(f"  ✓ 更新成功，长度: {len(content)} 字符")
                success_count += 1
            except Exception as e:
                print(f"  ✗ 更新失败: {e}")
                fail_count += 1
                fail_list.append(article)
        else:
            print(f"  ✗ 抓取内容为空")
            fail_count += 1
            fail_list.append(article)
        
        # 避免请求过快
        time.sleep(0.5)
    
    print(f"\n=== 抓取完成 ===")
    print(f"成功: {success_count} 篇")
    print(f"失败: {fail_count} 篇")
    
    if fail_list:
        print(f"\n失败文章列表:")
        for fail in fail_list:
            print(f"  - ID {fail['id']}: {fail['title']} ({fail['source_url']})")
        
        # 保存失败列表
        with open('/Users/moyi/moyi-admin/failed_articles.json', 'w', encoding='utf-8') as f:
            json.dump(fail_list, f, ensure_ascii=False, indent=2)
        print(f"\n失败文章列表已保存到 failed_articles.json")
    
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
