#!/usr/bin/env python3
"""
修复文章内容中的 Unicode 转义序列
"""

import psycopg2
from psycopg2.extras import RealDictCursor
import re

# 数据库配置
DB_CONFIG = {
    'host': 'postgres.orb.local',
    'port': 5432,
    'database': 'postgres',
    'user': 'postgres',
    'password': 'postgres',
}

def decode_unicode_escapes(text):
    """解码 Unicode 转义序列"""
    if not text:
        return text
    
    # 解码 \uXXXX 格式
    def replace_unicode(match):
        try:
            return chr(int(match.group(1), 16))
        except:
            return match.group(0)
    
    # 替换 \uXXXX 格式
    text = re.sub(r'\\u([0-9a-fA-F]{4})', replace_unicode, text)
    
    # 替换 \xXX 格式
    def replace_hex(match):
        try:
            return chr(int(match.group(1), 16))
        except:
            return match.group(0)
    
    text = re.sub(r'\\x([0-9a-fA-F]{2})', replace_hex, text)
    
    return text

def main():
    # 连接数据库
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        print("✓ 数据库连接成功\n")
    except Exception as e:
        print(f"数据库连接失败: {e}")
        return
    
    # 查找需要修复的文章
    cursor.execute("""
        SELECT id, title, content 
        FROM jianhui_org_articles 
        WHERE content LIKE '%\\u%' OR content LIKE '%\\x%'
        ORDER BY id
    """)
    articles = cursor.fetchall()
    
    print(f"找到 {len(articles)} 篇需要修复的文章\n")
    
    if not articles:
        print("没有需要修复的文章")
        return
    
    # 修复文章
    success_count = 0
    fail_count = 0
    
    for index, article in enumerate(articles):
        current = index + 1
        print(f"[{current}/{len(articles)}] 修复: {article['title']}")
        
        try:
            # 解码内容
            fixed_content = decode_unicode_escapes(article['content'])
            
            # 更新数据库
            cursor.execute(
                "UPDATE jianhui_org_articles SET content = %s, updated_at = NOW() WHERE id = %s",
                (fixed_content, article['id'])
            )
            conn.commit()
            print(f"  ✓ 修复成功")
            success_count += 1
        except Exception as e:
            print(f"  ✗ 修复失败: {e}")
            fail_count += 1
    
    print(f"\n=== 修复完成 ===")
    print(f"成功: {success_count} 篇")
    print(f"失败: {fail_count} 篇")
    
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
