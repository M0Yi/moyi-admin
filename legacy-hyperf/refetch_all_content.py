#!/usr/bin/env python3
"""
重新抓取所有文章的富文本内容
从 <article> 标签中提取
"""

import requests
import re
import psycopg2
from psycopg2.extras import RealDictCursor
import time
import json
import os

# 数据库配置
DB_CONFIG = {
    'host': 'postgres.orb.local',
    'port': 5432,
    'database': 'postgres',
    'user': 'postgres',
    'password': 'postgres',
}

# 进度文件
PROGRESS_FILE = '/Users/moyi/moyi-admin/refetch_progress.json'

def extract_article_content(html):
    """从HTML中提取文章内容（article标签中的内容）"""
    pattern = r'<article[^>]*>(.*?)</article>'
    match = re.search(pattern, html, re.DOTALL)
    
    if match:
        content = match.group(1).strip()
        if len(content) > 50:
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
        return extract_article_content(html)
    except Exception as e:
        return None

def save_progress(progress):
    """保存进度"""
    with open(PROGRESS_FILE, 'w', encoding='utf-8') as f:
        json.dump(progress, f, ensure_ascii=False, indent=2)

def load_progress():
    """加载进度"""
    if os.path.exists(PROGRESS_FILE):
        with open(PROGRESS_FILE, 'r', encoding='utf-8') as f:
            return json.load(f)
    return {}

def main():
    # 连接数据库
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        print("✓ 数据库连接成功\n")
    except Exception as e:
        print(f"数据库连接失败: {e}")
        return
    
    # 获取所有文章
    cursor.execute("""
        SELECT id, title, source_url 
        FROM jianhui_org_articles 
        ORDER BY id
    """)
    all_articles = cursor.fetchall()
    
    print(f"共 {len(all_articles)} 篇文章\n")
    
    # 加载进度
    progress = load_progress()
    last_id = progress.get('last_id', 0)
    success_count = progress.get('success_count', 0)
    fail_count = progress.get('fail_count', 0)
    
    # 过滤已处理的文章
    articles = [a for a in all_articles if a['id'] > last_id]
    
    if last_id > 0:
        print(f"从 ID {last_id} 继续，剩余 {len(articles)} 篇\n")
    
    # 开始抓取
    for index, article in enumerate(articles):
        current = index + 1
        total = len(articles)
        
        print(f"[{current}/{total}] ID {article['id']}: {article['title'][:30]}...")
        
        content = fetch_article_content(article['source_url'])
        
        if content:
            try:
                cursor.execute(
                    "UPDATE jianhui_org_articles SET content = %s, updated_at = NOW() WHERE id = %s",
                    (content, article['id'])
                )
                conn.commit()
                print(f"  ✓ 成功，长度: {len(content)} 字符")
                success_count += 1
            except Exception as e:
                print(f"  ✗ 更新失败: {e}")
                fail_count += 1
        else:
            print(f"  ✗ 未找到内容")
            fail_count += 1
        
        # 保存进度
        save_progress({
            'last_id': article['id'],
            'success_count': success_count,
            'fail_count': fail_count,
            'last_update': time.strftime('%Y-%m-%d %H:%M:%S')
        })
        
        time.sleep(0.3)
    
    print(f"\n=== 完成 ===")
    print(f"成功: {success_count}")
    print(f"失败: {fail_count}")
    
    # 清除进度文件
    if os.path.exists(PROGRESS_FILE):
        os.remove(PROGRESS_FILE)
    
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
