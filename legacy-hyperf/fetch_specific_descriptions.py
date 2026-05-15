#!/usr/bin/env python3
"""
从文章内容提取具体的描述
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
    """从文章页面提取具体描述"""
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Referer': 'http://www.jianhuicishan.org/'
    }
    
    try:
        response = requests.get(url, headers=headers, timeout=30)
        html = response.text
        
        # 方法1: 从文章内容的desc类提取（这是最准确的）
        desc_match = re.search(r'<div class="desc"[^>]*>(.*?)</div>', html, re.DOTALL)
        if desc_match:
            desc = re.sub(r'<[^>]+>', '', desc_match.group(1)).strip()
            if len(desc) > 10 and '致敬困境中的行善者' not in desc:
                return desc[:150] if len(desc) > 150 else desc
        
        # 方法2: 从文章正文提取第一段有意义的内容
        content_match = re.search(r'<div class="content"[^>]*>(.*?)</div>', html, re.DOTALL)
        if content_match:
            content = content_match.group(1)
            # 提取所有p标签
            paragraphs = re.findall(r'<p[^>]*>(.*?)</p>', content, re.DOTALL)
            for p in paragraphs:
                text = re.sub(r'<[^>]+>', '', p).strip()
                # 过滤掉太短或无意义的内容
                if len(text) > 20 and '点击' not in text and 'Copyright' not in text:
                    return text[:150] + '...' if len(text) > 150 else text
        
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
        SELECT id, title, source_url, description 
        FROM jianhui_org_articles 
        WHERE category_id = 34 
        ORDER BY id
    """)
    articles = cursor.fetchall()
    
    print(f"共 {len(articles)} 篇文章\n")
    
    success_count = 0
    skip_count = 0
    fail_count = 0
    
    for index, article in enumerate(articles):
        current = index + 1
        print(f"[{current}/{len(articles)}] {article['title']}")
        
        # 检查现有描述是否是通用描述
        if article['description'] and '致敬困境中的行善者' not in article['description']:
            print(f"  ⊘ 已有具体描述")
            skip_count += 1
            continue
        
        # 获取具体描述
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
            print(f"  ✗ 未找到具体描述")
            fail_count += 1
        
        time.sleep(0.3)
    
    print(f"\n=== 处理完成 ===")
    print(f"成功: {success_count} 篇")
    print(f"跳过: {skip_count} 篇")
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
        print(f"\nID {row['id']}: {row['title']}")
        print(f"  描述: {row['description'][:100]}...")
    
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
