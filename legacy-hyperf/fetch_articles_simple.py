#!/usr/bin/env python3
"""
简化的文章抓取脚本 - 直接提取文本内容
"""

import requests
import re
import psycopg2
from psycopg2.extras import RealDictCursor
import time
import json

# 数据库配置
DB_CONFIG = {
    'host': 'postgres.orb.local',
    'port': 5432,
    'database': 'postgres',
    'user': 'postgres',
    'password': 'postgres',
}

def fetch_article_content(url):
    """抓取文章内容 - 直接提取文本"""
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        response = requests.get(url, headers=headers, timeout=30, verify=False)
        
        if response.status_code != 200:
            return None
        
        html = response.text
        
        # 使用简单的文本提取方法
        # 查找 article-detail 之后的所有文本内容
        pattern = r'<div class="article-detail"[^>]*>(.*?)</div>\s*</div>\s*</div>'
        matches = re.findall(pattern, html, re.DOTALL)
        
        if not matches:
            # 尝试另一种模式
            pattern = r'任前公示(.*?)接访时间'
            matches = re.findall(pattern, html, re.DOTALL)
        
        if not matches:
            # 直接提取所有文本
            # 移除脚本和样式
            html = re.sub(r'<script[^>]*>.*?</script>', '', html, flags=re.DOTALL)
            html = re.sub(r'<style[^>]*>.*?</style>', '', html, flags=re.DOTALL)
            
            # 查找文章内容区域
            pattern = r'按照建辉基金会.*?接访时间'
            matches = re.findall(pattern, html, re.DOTALL)
        
        if matches:
            content = matches[0]
            # 清理 HTML 标签
            content = re.sub(r'<[^>]+>', ' ', content)
            content = re.sub(r'&nbsp;', ' ', content)
            content = re.sub(r'\s+', ' ', content).strip()
            return f'<p>{content}</p>' if content else None
        
        return None
        
    except Exception as e:
        print(f"  ✗ 抓取失败: {e}")
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
    
    # 检查空内容的文章
    cursor.execute("SELECT COUNT(*) as count FROM jianhui_org_articles WHERE content IS NULL OR TRIM(content) = ''")
    result = cursor.fetchone()
    empty_count = result['count']
    print(f"空内容文章数量: {empty_count}\n")
    
    if empty_count == 0:
        print("所有文章都有内容，无需抓取")
        return
    
    # 获取所有空内容的文章
    cursor.execute("""
        SELECT id, title, source_url 
        FROM jianhui_org_articles 
        WHERE content IS NULL OR TRIM(content) = '' 
        ORDER BY id
    """)
    articles = cursor.fetchall()
    
    print(f"共找到 {len(articles)} 篇空内容文章\n")
    
    # 测试抓取第一篇文章
    test_article = articles[0]
    print(f"=== 测试抓取第一篇文章 ===")
    print(f"文章ID: {test_article['id']}")
    print(f"标题: {test_article['title']}")
    print(f"URL: {test_article['source_url']}\n")
    
    content = fetch_article_content(test_article['source_url'])
    if content:
        print(f"✓ 成功抓取内容，长度: {len(content)} 字符")
        print(f"内容预览:\n{content[:300]}...\n")
    else:
        print("✗ 抓取内容失败\n")
    
    # 询问是否继续抓取所有文章
    answer = input("是否继续抓取所有文章？(yes/no): ").strip().lower()
    if answer != 'yes':
        print("已取消抓取")
        return
    
    # 开始抓取所有文章
    print("\n=== 开始抓取所有文章 ===")
    success_count = 0
    fail_count = 0
    fail_list = []
    
    for index, article in enumerate(articles):
        current = index + 1
        print(f"[{current}/{empty_count}] 抓取: {article['title']}")
        
        content = fetch_article_content(article['source_url'])
        
        if content and content.strip():
            # 更新数据库
            try:
                cursor.execute(
                    "UPDATE jianhui_org_articles SET content = %s, updated_at = NOW() WHERE id = %s",
                    (content, article['id'])
                )
                conn.commit()
                print(f"  ✓ 更新成功，长度: {len(content)} 字符")
                success_count += 1
            except Exception as e:
                print(f"  ✗ 更新失败: {e}")
                fail_count += 1
                fail_list.append(article)
        else:
            print(f"  ✗ 抓取内容为空")
            fail_count += 1
            fail_list.append(article)
        
        # 避免请求过快
        time.sleep(0.5)
    
    print(f"\n=== 抓取完成 ===")
    print(f"成功: {success_count} 篇")
    print(f"失败: {fail_count} 篇")
    
    if fail_list:
        print(f"\n失败文章列表:")
        for fail in fail_list:
            print(f"  - ID {fail['id']}: {fail['title']} ({fail['source_url']})")
        
        # 保存失败列表
        with open('/Users/moyi/moyi-admin/failed_articles.json', 'w', encoding='utf-8') as f:
            json.dump(fail_list, f, ensure_ascii=False, indent=2)
        print(f"\n失败文章列表已保存到 failed_articles.json")
    
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
