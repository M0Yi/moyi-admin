#!/usr/bin/env python3
"""
从文章列表页面获取人物封面图片
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

def fetch_list_page_covers():
    """从列表页面获取文章封面"""
    url = 'http://www.jianhuicishan.org/article/findGoodPeople/list'
    
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    }
    
    response = requests.get(url, headers=headers, timeout=30, verify=False)
    html = response.text
    
    # 保存HTML以便分析
    with open('/tmp/list_page.html', 'w', encoding='utf-8') as f:
        f.write(html)
    
    print(f"列表页面HTML长度: {len(html)}")
    
    # 查找文章链接和对应的图片
    # 模式: 查找包含文章链接和图片的区域
    article_pattern = r'<a[^>]+href="(/article/findGoodPeople/detail/\d+)"[^>]*>.*?<img[^>]+src="([^"]+)"[^>]*>.*?</a>'
    matches = re.findall(article_pattern, html, re.DOTALL)
    
    print(f"找到 {len(matches)} 个文章链接和图片:")
    results = {}
    for link, img in matches[:10]:
        article_id = re.search(r'/detail/(\d+)', link)
        if article_id:
            aid = article_id.group(1)
            results[aid] = img
            print(f"  ID {aid}: {img[:60]}...")
    
    return results

def main():
    # 获取列表页面的封面
    covers = fetch_list_page_covers()
    
    if not covers:
        print("未找到封面图片")
        return
    
    # 连接数据库
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        print("\n✓ 数据库连接成功")
    except Exception as e:
        print(f"数据库连接失败: {e}")
        return
    
    # 获取需要更新的文章
    cursor.execute("""
        SELECT id, title, source_url 
        FROM jianhui_org_articles 
        WHERE category_id = 34 
        ORDER BY id
    """)
    articles = cursor.fetchall()
    
    print(f"\n开始更新文章封面...")
    success_count = 0
    
    for article in articles:
        # 从source_url提取文章ID
        url_match = re.search(r'/detail/(\d+)', article['source_url'])
        if url_match:
            source_id = url_match.group(1)
            if source_id in covers:
                cover_url = covers[source_id]
                try:
                    cursor.execute(
                        "UPDATE jianhui_org_articles SET cover_image = %s, updated_at = NOW() WHERE id = %s",
                        (cover_url, article['id'])
                    )
                    conn.commit()
                    print(f"  ✓ {article['title']}: {cover_url[:50]}...")
                    success_count += 1
                except Exception as e:
                    print(f"  ✗ {article['title']}: {e}")
    
    print(f"\n=== 更新完成 ===")
    print(f"成功更新: {success_count} 篇")
    
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
