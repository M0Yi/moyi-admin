#!/usr/bin/env python3
"""
从HTML中提取文章内容
正确提取 <article> 标签中的内容
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

def extract_article_content(html):
    """从HTML中提取文章内容（article标签中的内容）"""
    
    # 查找 <article ...>...</article> 标签
    # 使用非贪婪匹配
    pattern = r'<article[^>]*>(.*?)</article>'
    match = re.search(pattern, html, re.DOTALL)
    
    if match:
        content = match.group(1).strip()
        if len(content) > 50:  # 确保是有效内容
            return content
    
    return None

def fetch_article_content(url):
    """抓取文章内容"""
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Referer': 'http://www.jianhuicishan.org/'
    }
    
    try:
        response = requests.get(url, headers=headers, timeout=30)
        html = response.text
        
        # 提取文章内容
        content = extract_article_content(html)
        return content
        
    except Exception as e:
        print(f"  ✗ 请求失败: {e}")
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
    
    # 测试抓取5篇文章
    cursor.execute("""
        SELECT id, title, source_url 
        FROM jianhui_org_articles 
        WHERE category_id = 34 
        ORDER BY id
        LIMIT 5
    """)
    articles = cursor.fetchall()
    
    print(f"=== 测试抓取 {len(articles)} 篇文章 ===\n")
    
    for index, article in enumerate(articles):
        current = index + 1
        print(f"[{current}/{len(articles)}] {article['title']}")
        
        content = fetch_article_content(article['source_url'])
        
        if content:
            print(f"  ✓ 成功提取内容，长度: {len(content)} 字符")
            # 显示内容预览
            preview = content[:200].replace('\n', ' ')
            print(f"  内容预览: {preview}...")
            
            # 更新数据库
            try:
                cursor.execute(
                    "UPDATE jianhui_org_articles SET content = %s, updated_at = NOW() WHERE id = %s",
                    (content, article['id'])
                )
                conn.commit()
                print(f"  ✓ 数据库已更新")
            except Exception as e:
                print(f"  ✗ 更新失败: {e}")
        else:
            print(f"  ✗ 未找到内容")
        
        print()
        time.sleep(0.5)
    
    cursor.close()
    conn.close()
    
    print("=== 测试完成 ===")

if __name__ == '__main__':
    main()
