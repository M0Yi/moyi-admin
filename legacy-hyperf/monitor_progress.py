#!/usr/bin/env python3
"""
监控文章抓取进度
"""

import json
import os
import time
import psycopg2
from psycopg2.extras import RealDictCursor

# 数据库配置
DB_CONFIG = {
    'host': 'postgres.orb.local',
    'port': 5432,
    'database': 'postgres',
    'user': 'postgres',
    'password': 'postgres',
}

# 进度文件
PROGRESS_FILE = '/Users/moyi/moyi-admin/fetch_progress.json'

def get_db_stats():
    """获取数据库统计"""
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        
        cursor.execute("""
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN content IS NOT NULL AND TRIM(content) != '' THEN 1 END) as has_content,
                COUNT(CASE WHEN content IS NULL OR TRIM(content) = '' THEN 1 END) as empty_content
            FROM jianhui_org_articles
        """)
        result = cursor.fetchone()
        
        cursor.close()
        conn.close()
        
        return result
    except Exception as e:
        return None

def get_progress():
    """获取抓取进度"""
    if os.path.exists(PROGRESS_FILE):
        with open(PROGRESS_FILE, 'r', encoding='utf-8') as f:
            return json.load(f)
    return {}

def main():
    print("=== 文章抓取进度监控 ===\n")
    
    while True:
        # 获取数据库统计
        db_stats = get_db_stats()
        if db_stats:
            print(f"数据库统计:")
            print(f"  总文章数: {db_stats['total']}")
            print(f"  有内容: {db_stats['has_content']}")
            print(f"  无内容: {db_stats['empty_content']}")
        
        # 获取抓取进度
        progress = get_progress()
        if progress:
            print(f"\n抓取进度:")
            print(f"  最后处理ID: {progress.get('last_id', 0)}")
            print(f"  成功: {progress.get('success_count', 0)}")
            print(f"  失败: {progress.get('fail_count', 0)}")
            print(f"  最后更新: {progress.get('last_update', 'N/A')}")
        
        # 计算进度百分比
        if db_stats and db_stats['total'] > 0:
            percentage = (db_stats['has_content'] / db_stats['total']) * 100
            print(f"\n完成度: {percentage:.1f}%")
        
        print("\n" + "="*50 + "\n")
        
        # 每5秒更新一次
        time.sleep(5)

if __name__ == '__main__':
    main()
