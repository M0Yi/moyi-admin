#!/usr/bin/env python3
"""
提取行善者生命故事文章的封面图片
从文章内容中提取第一张图片作为封面
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

def extract_first_image(content):
    """从文章内容中提取第一张图片 URL"""
    if not content:
        return None
    
    # 匹配 img 标签的 src 属性
    img_pattern = r'<img[^>]+src=["\']([^"\']+)["\'][^>]*>'
    matches = re.findall(img_pattern, content, re.IGNORECASE)
    
    if matches:
        # 返回第一张图片的 URL
        return matches[0]
    
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
    
    # 查询行善者生命故事分类的文章
    cursor.execute("""
        SELECT a.id, a.title, a.content, a.cover_image, a.source_url
        FROM jianhui_org_articles a
        LEFT JOIN jianhui_org_categories c ON a.category_id = c.id
        WHERE c.name = '行善者生命故事'
        ORDER BY a.id
    """)
    articles = cursor.fetchall()
    
    print(f"找到 {len(articles)} 篇行善者生命故事文章\n")
    
    # 统计
    success_count = 0
    skip_count = 0
    fail_count = 0
    no_image_count = 0
    
    for index, article in enumerate(articles):
        current = index + 1
        print(f"[{current}/{len(articles)}] 处理: {article['title'][:30]}...")
        
        # 检查是否已有封面
        if article['cover_image']:
            print(f"  ⊘ 已有封面，跳过")
            skip_count += 1
            continue
        
        # 从内容中提取第一张图片
        first_image = extract_first_image(article['content'])
        
        if first_image:
            # 更新数据库
            try:
                cursor.execute(
                    "UPDATE jianhui_org_articles SET cover_image = %s, updated_at = NOW() WHERE id = %s",
                    (first_image, article['id'])
                )
                conn.commit()
                print(f"  ✓ 设置封面: {first_image[:50]}...")
                success_count += 1
            except Exception as e:
                print(f"  ✗ 更新失败: {e}")
                fail_count += 1
        else:
            print(f"  ✗ 未找到图片")
            no_image_count += 1
    
    print(f"\n=== 处理完成 ===")
    print(f"成功设置封面: {success_count} 篇")
    print(f"已有封面跳过: {skip_count} 篇")
    print(f"未找到图片: {no_image_count} 篇")
    print(f"更新失败: {fail_count} 篇")
    
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
