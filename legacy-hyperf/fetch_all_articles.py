#!/usr/bin/env python3
"""
建辉文章批量抓取脚本
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
PROGRESS_FILE = '/Users/moyi/moyi-admin/fetch_progress.json'
LOG_FILE = '/Users/moyi/moyi-admin/fetch_articles.log'

def log_message(message):
    """记录日志"""
    timestamp = time.strftime('%Y-%m-%d %H:%M:%S')
    log_line = f"[{timestamp}] {message}\n"
    print(log_line, end='')
    with open(LOG_FILE, 'a', encoding='utf-8') as f:
        f.write(log_line)

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

def fetch_article_content(url):
    """抓取文章内容"""
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        response = requests.get(url, headers=headers, timeout=30, verify=False)
        
        if response.status_code != 200:
            return None
        
        html = response.text
        
        # 移除脚本和样式
        html = re.sub(r'<script[^>]*>.*?</script>', '', html, flags=re.DOTALL)
        html = re.sub(r'<style[^>]*>.*?</style>', '', html, flags=re.DOTALL)
        
        # 查找文章内容区域
        # 尝试多种模式
        patterns = [
            r'按照.*?接访时间',
            r'20\d{2}年.*?20\d{2}年',
            r'项目进展.*?公示',
            r'建辉.*?基金会',
        ]
        
        content = None
        for pattern in patterns:
            matches = re.findall(pattern, html, re.DOTALL)
            if matches and len(matches[0]) > 50:
                content = matches[0]
                break
        
        if not content:
            # 直接提取所有文本
            # 查找可能的文章内容
            text_content = re.sub(r'<[^>]+>', ' ', html)
            text_content = re.sub(r'&nbsp;', ' ', text_content)
            text_content = re.sub(r'\s+', ' ', text_content).strip()
            
            # 查找有意义的内容段落
            paragraphs = re.split(r'[。\n]', text_content)
            meaningful_paragraphs = [p.strip() for p in paragraphs if len(p.strip()) > 20]
            
            if meaningful_paragraphs:
                content = '。\n'.join(meaningful_paragraphs[:10])  # 取前10段
        
        if content and len(content) > 50:
            # 清理内容
            content = re.sub(r'<[^>]+>', ' ', content)
            content = re.sub(r'&nbsp;', ' ', content)
            content = re.sub(r'\s+', ' ', content).strip()
            return f'<p>{content}</p>'
        
        return None
        
    except Exception as e:
        log_message(f"  ✗ 抓取失败: {e}")
        return None

def main():
    # 连接数据库
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        log_message("数据库连接成功")
    except Exception as e:
        log_message(f"数据库连接失败: {e}")
        return
    
    # 检查空内容的文章
    cursor.execute("SELECT COUNT(*) as count FROM jianhui_org_articles WHERE content IS NULL OR TRIM(content) = ''")
    result = cursor.fetchone()
    empty_count = result['count']
    log_message(f"空内容文章数量: {empty_count}")
    
    if empty_count == 0:
        log_message("所有文章都有内容，无需抓取")
        return
    
    # 获取所有空内容的文章
    cursor.execute("""
        SELECT id, title, source_url 
        FROM jianhui_org_articles 
        WHERE content IS NULL OR TRIM(content) = '' 
        ORDER BY id
    """)
    articles = cursor.fetchall()
    
    log_message(f"共找到 {len(articles)} 篇空内容文章")
    
    # 加载进度
    progress = load_progress()
    last_id = progress.get('last_id', 0)
    success_count = progress.get('success_count', 0)
    fail_count = progress.get('fail_count', 0)
    fail_list = progress.get('fail_list', [])
    
    # 过滤已处理的文章
    if last_id > 0:
        articles = [a for a in articles if a['id'] > last_id]
        log_message(f"从 ID {last_id} 继续，剩余 {len(articles)} 篇文章")
    
    # 开始抓取
    log_message("开始抓取文章内容")
    total_count = len(articles)
    
    for index, article in enumerate(articles):
        current = index + 1
        article_id = article['id']
        
        log_message(f"[{current}/{total_count}] ID {article_id}: {article['title']}")
        
        content = fetch_article_content(article['source_url'])
        
        if content and content.strip():
            # 更新数据库
            try:
                cursor.execute(
                    "UPDATE jianhui_org_articles SET content = %s, updated_at = NOW() WHERE id = %s",
                    (content, article_id)
                )
                conn.commit()
                log_message(f"  ✓ 更新成功，长度: {len(content)} 字符")
                success_count += 1
            except Exception as e:
                log_message(f"  ✗ 更新失败: {e}")
                fail_count += 1
                fail_list.append(article)
        else:
            log_message(f"  ✗ 抓取内容为空")
            fail_count += 1
            fail_list.append(article)
        
        # 保存进度
        save_progress({
            'last_id': article_id,
            'success_count': success_count,
            'fail_count': fail_count,
            'fail_list': fail_list,
            'last_update': time.strftime('%Y-%m-%d %H:%M:%S'),
        })
        
        # 避免请求过快
        time.sleep(0.5)
    
    # 完成
    log_message(f"\n=== 抓取完成 ===")
    log_message(f"成功: {success_count} 篇")
    log_message(f"失败: {fail_count} 篇")
    
    if fail_list:
        log_message(f"\n失败文章列表:")
        for fail in fail_list:
            log_message(f"  - ID {fail['id']}: {fail['title']} ({fail['source_url']})")
        
        # 保存失败列表
        with open('/Users/moyi/moyi-admin/failed_articles.json', 'w', encoding='utf-8') as f:
            json.dump(fail_list, f, ensure_ascii=False, indent=2)
        log_message(f"\n失败文章列表已保存到 failed_articles.json")
    
    # 清除进度文件
    if os.path.exists(PROGRESS_FILE):
        os.remove(PROGRESS_FILE)
    
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
