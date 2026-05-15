#!/usr/bin/env python3
"""
验证并更新封面图片
确保所有封面图片都是有效的人物图片
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

def verify_image_url(url):
    """验证图片URL是否有效"""
    if not url:
        return False
    
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Referer': 'http://www.jianhuicishan.org/'
    }
    
    try:
        response = requests.head(url, headers=headers, timeout=10, allow_redirects=True)
        return response.status_code == 200 and 'image' in response.headers.get('Content-Type', '')
    except:
        return False

def extract_cover_from_article(url):
    """从文章页面提取封面图片"""
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Referer': 'http://www.jianhuicishan.org/'
    }
    
    try:
        response = requests.get(url, headers=headers, timeout=30)
        html = response.text
        
        # 查找所有 gongyila 图片
        all_images = re.findall(r'src="(https?://[^"]*gongyila\.com[^"]+)"', html)
        
        # 过滤掉logo、icon、banner等
        filtered = [img for img in all_images if not any(x in img.lower() for x in ['logo', 'icon', 'banner', 'code', 'nuxt'])]
        
        # 优先选择 Uploads 或 tenant 目录下的图片（通常是人物照片）
        person_images = [img for img in filtered if '/Uploads/' in img or '/tenant/' in img]
        
        if person_images:
            return person_images[0]
        elif filtered:
            return filtered[0]
        
        return None
        
    except Exception as e:
        print(f"    请求失败: {e}")
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
    
    # 获取"发现你身边的行善者"分类的文章
    cursor.execute("""
        SELECT id, title, source_url, cover_image 
        FROM jianhui_org_articles 
        WHERE category_id = 34 
        ORDER BY id
    """)
    articles = cursor.fetchall()
    
    print(f"=== 验证和更新封面图片 ===\n")
    print(f"共 {len(articles)} 篇文章\n")
    
    updated_count = 0
    valid_count = 0
    invalid_count = 0
    
    for index, article in enumerate(articles):
        current = index + 1
        print(f"[{current}/{len(articles)}] {article['title']}")
        
        # 验证现有封面
        if article['cover_image']:
            if verify_image_url(article['cover_image']):
                print(f"  ✓ 现有封面有效")
                valid_count += 1
                continue
            else:
                print(f"  ✗ 现有封面无效，重新提取...")
        
        # 提取新封面
        new_cover = extract_cover_from_article(article['source_url'])
        
        if new_cover and verify_image_url(new_cover):
            # 更新数据库
            try:
                cursor.execute(
                    "UPDATE jianhui_org_articles SET cover_image = %s, updated_at = NOW() WHERE id = %s",
                    (new_cover, article['id'])
                )
                conn.commit()
                print(f"  ✓ 更新封面: {new_cover[:50]}...")
                updated_count += 1
            except Exception as e:
                print(f"  ✗ 更新失败: {e}")
                invalid_count += 1
        else:
            print(f"  ✗ 未找到有效封面")
            invalid_count += 1
        
        time.sleep(0.3)
    
    print(f"\n=== 处理完成 ===")
    print(f"有效封面: {valid_count} 篇")
    print(f"更新封面: {updated_count} 篇")
    print(f"无效/无封面: {invalid_count} 篇")
    
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
