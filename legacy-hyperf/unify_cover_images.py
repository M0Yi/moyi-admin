#!/usr/bin/env python3
"""
统一处理封面图片
确保所有有封面的文章在内容开头都包含封面图片
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

def main():
    # 连接数据库
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        print("✓ 数据库连接成功\n")
    except Exception as e:
        print(f"数据库连接失败: {e}")
        return
    
    # 获取有封面图片的文章
    cursor.execute("""
        SELECT id, title, cover_image, content 
        FROM jianhui_org_articles 
        WHERE category_id = 34 
        AND cover_image IS NOT NULL 
        AND cover_image != ''
        ORDER BY id
    """)
    articles = cursor.fetchall()
    
    print(f"找到 {len(articles)} 篇有封面的文章\n")
    
    success_count = 0
    
    for index, article in enumerate(articles):
        current = index + 1
        content = article['content'] or ''
        cover_image = article['cover_image']
        
        # 检查内容中是否已经有这个封面图片
        if cover_image in content:
            print(f"[{current}/{len(articles)}] {article['title']}: 已有封面")
            continue
        
        # 在内容开头添加封面图片
        cover_img_tag = f'<img src="{cover_image}" class="cover-image" alt="{article["title"]}">\n'
        new_content = cover_img_tag + content
        
        # 更新数据库
        try:
            cursor.execute(
                "UPDATE jianhui_org_articles SET content = %s, updated_at = NOW() WHERE id = %s",
                (new_content, article['id'])
            )
            conn.commit()
            print(f"[{current}/{len(articles)}] {article['title']}: ✓ 添加封面")
            success_count += 1
        except Exception as e:
            print(f"[{current}/{len(articles)}] {article['title']}: ✗ 更新失败 - {e}")
    
    print(f"\n=== 处理完成 ===")
    print(f"成功添加封面: {success_count} 篇")
    
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
