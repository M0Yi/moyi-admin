#!/usr/bin/env python3
"""
从原网站抓取文章描述
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

def fetch_article_description(url):
    """从文章页面提取描述"""
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Referer': 'http://www.jianhuicishan.org/'
    }
    
    try:
        response = requests.get(url, headers=headers, timeout=30)
        html = response.text
        
        # 方法1: 从HTML的meta description提取
        meta_match = re.search(r'<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']', html)
        if meta_match:
            desc = meta_match.group(1).strip()
            if len(desc) > 10:
                return desc
        
        # 方法2: 从文章内容的desc类提取
        desc_match = re.search(r'<div class="desc"[^>]*>(.*?)</div>', html, re.DOTALL)
        if desc_match:
            desc = re.sub(r'<[^>]+>', '', desc_match.group(1)).strip()
            if len(desc) > 10:
                return desc
        
        # 方法3: 从文章正文第一段提取
        content_match = re.search(r'<div class="content"[^>]*>(.*?)</div>', html, re.DOTALL)
        if content_match:
            content = content_match.group(1)
            # 提取第一个p标签的内容
            p_match = re.search(r'<p[^>]*>(.*?)</p>', content, re.DOTALL)
            if p_match:
                desc = re.sub(r'<[^>]+>', '', p_match.group(1)).strip()
                if len(desc) > 10:
                    # 截取前100个字符作为描述
                    return desc[:100] + '...' if len(desc) > 100 else desc
        
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
    
    # 获取没有描述的文章
    cursor.execute("""
        SELECT id, title, source_url, description 
        FROM jianhui_org_articles 
        WHERE category_id = 34 
        AND (description IS NULL OR description = '')
        ORDER BY id
    """)
    articles = cursor.fetchall()
    
    print(f"找到 {len(articles)} 篇没有描述的文章\n")
    
    success_count = 0
    fail_count = 0
    
    for index, article in enumerate(articles):
        current = index + 1
        print(f"[{current}/{len(articles)}] {article['title']}")
        
        # 获取描述
        description = fetch_article_description(article['source_url'])
        
        if description:
            # 更新数据库
            try:
                cursor.execute(
                    "UPDATE jianhui_org_articles SET description = %s, updated_at = NOW() WHERE id = %s",
                    (description, article['id'])
                )
                conn.commit()
                print(f"  ✓ 描述: {description[:50]}...")
                success_count += 1
            except Exception as e:
                print(f"  ✗ 更新失败: {e}")
                fail_count += 1
        else:
            print(f"  ✗ 未找到描述")
            fail_count += 1
        
        time.sleep(0.3)
    
    print(f"\n=== 处理完成 ===")
    print(f"成功: {success_count} 篇")
    print(f"失败: {fail_count} 篇")
    
    # 显示结果示例
    print(f"\n=== 描述示例 ===")
    cursor.execute("""
        SELECT id, title, description 
        FROM jianhui_org_articles 
        WHERE category_id = 34 AND description IS NOT NULL AND description != ''
        LIMIT 5
    """)
    for row in cursor.fetchall():
        print(f"ID {row['id']}: {row['title']}")
        print(f"  描述: {row['description'][:80]}...")
    
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
