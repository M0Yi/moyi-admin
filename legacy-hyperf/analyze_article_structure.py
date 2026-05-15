#!/usr/bin/env python3
"""
分析文章页面结构，找到人物图片
"""

import requests
import re

url = 'http://www.jianhuicishan.org/article/findGoodPeople/detail/10996'

headers = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
}

response = requests.get(url, headers=headers, timeout=30, verify=False)
html = response.text

print("=== 查找所有 gongyila 图片 ===")
all_images = re.findall(r'src="(https?://[^"]*gongyila\.com[^"]+)"', html)
print(f"找到 {len(all_images)} 张 gongyila 图片:")
for i, img in enumerate(all_images):
    print(f"  {i+1}. {img}")

print("\n=== 查找 article-detail 区域 ===")
# 查找 article-detail 开始到 side-content 之间的内容
article_match = re.search(r'<div class="article-detail"[^>]*>(.*?)<div class="side-content"', html, re.DOTALL)
if article_match:
    article_html = article_match.group(1)
    print(f"找到 article-detail 区域，长度: {len(article_html)}")
    
    # 提取所有图片
    images = re.findall(r'<img[^>]+src=["\']([^"\']+)["\'][^>]*>', article_html)
    print(f"article-detail 区域找到 {len(images)} 张图片:")
    for i, img in enumerate(images):
        print(f"  {i+1}. {img}")
else:
    print("未找到 article-detail 区域")
    
    # 尝试其他模式
    article_match = re.search(r'class="article-detail"(.*?)class="side-content"', html, re.DOTALL)
    if article_match:
        article_html = article_match.group(1)
        print(f"使用备用模式找到，长度: {len(article_html)}")
        images = re.findall(r'<img[^>]+src=["\']([^"\']+)["\'][^>]*>', article_html)
        print(f"找到 {len(images)} 张图片:")
        for i, img in enumerate(images):
            print(f"  {i+1}. {img}")

print("\n=== 查找带有人物图片的区域 ===")
# 查找可能包含人物图片的区域（通常是第一张大图）
person_image_patterns = [
    r'class="[^"]*avatar[^"]*"[^>]*src="([^"]+)"',
    r'class="[^"]*photo[^"]*"[^>]*src="([^"]+)"',
    r'class="[^"]*person[^"]*"[^>]*src="([^"]+)"',
    r'<img[^>]+src="(https://file\.gongyila\.com/Uploads/editor/[^"]+)"[^>]*>',
]

for pattern in person_image_patterns:
    matches = re.findall(pattern, html)
    if matches:
        print(f"模式 '{pattern[:30]}...' 找到 {len(matches)} 张图片:")
        for i, img in enumerate(matches[:3]):
            print(f"  {i+1}. {img}")
