#!/usr/bin/env python3
"""
从文章详情页面提取人物封面图片
分析HTML结构找到人物图片
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

def extract_person_image_from_article(url):
    """从文章详情页面提取人物图片"""
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    }
    
    try:
        response = requests.get(url, headers=headers, timeout=30, verify=False)
        html = response.text
        
        # 方法1: 查找 image-dev.gongyila.com 图片（通常是人物头像）
        dev_images = re.findall(r'src="(http://image-dev\.gongyila\.com/tenant/[^"]+)"', html)
        
        # 方法2: 查找 image.gongyila.com 图片
        new_images = re.findall(r'src="(https://image\.gongyila\.com/oss/[^"]+)"', html)
        
        # 方法3: 查找 file.gongyila.com 的 Uploads 图片
        old_images = re.findall(r'src="(https://file\.gongyila\.com/Uploads/[^"]+)"', html)
        
        # 合并所有图片，优先使用新域名的图片
        all_images = new_images + dev_images + old_images
        
        # 过滤掉小图标和logo
        filtered_images = [img for img in all_images if not any(x in img.lower() for x in ['logo', 'icon', 'banner', 'code'])]
        
        # 返回第一张图片作为人物封面
        return filtered_images[0] if filtered_images else None
        
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
    
    # 获取"发现你身边的行善者"分类的文章
    cursor.execute("""
        SELECT id, title, source_url, cover_image 
        FROM jianhui_org_articles 
        WHERE category_id = 34 
        ORDER BY id
    """)
    articles = cursor.fetchall()
    
    print(f"找到 {len(articles)} 篇文章\n")
    
    success_count = 0
    skip_count = 0
    fail_count = 0
    
    for index, article in enumerate(articles):
        current = index + 1
        print(f"[{current}/{len(articles)}] {article['title']}")
        
        # 提取人物图片
        person_image = extract_person_image_from_article(article['source_url'])
        
        if person_image:
            # 检查是否与现有封面相同
            if article['cover_image'] == person_image:
                print(f"  ⊘ 封面未变化")
                skip_count += 1
                continue
            
            # 更新数据库
            try:
                cursor.execute(
                    "UPDATE jianhui_org_articles SET cover_image = %s, updated_at = NOW() WHERE id = %s",
                    (person_image, article['id'])
                )
                conn.commit()
                print(f"  ✓ 更新封面: {person_image[:60]}...")
                success_count += 1
            except Exception as e:
                print(f"  ✗ 更新失败: {e}")
                fail_count += 1
        else:
            print(f"  ✗ 未找到人物图片")
            fail_count += 1
        
        time.sleep(0.3)
    
    print(f"\n=== 处理完成 ===")
    print(f"成功更新: {success_count} 篇")
    print(f"跳过(无变化): {skip_count} 篇")
    print(f"失败: {fail_count} 篇")
    
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
