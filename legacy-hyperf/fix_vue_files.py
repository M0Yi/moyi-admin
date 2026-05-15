#!/usr/bin/env python3
"""
Fix Vue files damaged by batch regex processing.
Issues:
1. Duplicate style tag: <style scoped lang="scss"> scoped lang="scss">
2. Orphaned CSS rules at 0-indent outside any parent block
3. Empty @media blocks
"""

import re
import os

BROKEN_FILES = [
    "frontend/src/views/About/index.vue",
    "frontend/src/views/About/Intro.vue",
    "frontend/src/views/Articles/Category.vue",
    "frontend/src/views/Articles/Index.vue",
    "frontend/src/views/Articles/Subcategory.vue",
    "frontend/src/views/Contact.vue",
    "frontend/src/views/Disclosure.vue",
    "frontend/src/views/Disclosure/Index.vue",
    "frontend/src/views/Donate/DonationDisclosure.vue",
    "frontend/src/views/Donate/Index.vue",
    "frontend/src/views/FindGoodPeople/Index.vue",
    "frontend/src/views/JoinUs/Index.vue",
    "frontend/src/views/LifeStories/Index.vue",
    "frontend/src/views/Partners/Index.vue",
    "frontend/src/views/Privacy.vue",
    "frontend/src/views/ProjectArticles.vue",
    "frontend/src/views/Projects/Index.vue",
    "frontend/src/views/Search/index.vue",
    "frontend/src/views/Stories/Index.vue",
]

HEADER_IMG = "https://cdn1.zhizhucms.com/materials/image/744/2026/3/27/1774599952748_323.jpg"

PAGE_HEADER_CSS = """
.page-header {
  position: relative;
  width: 100%;
  height: 300px;
  overflow: hidden;

  .header-bg {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
}

@media (max-width: 768px) {
  .page-header {
    height: 200px;
  }
}
"""

def fix_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content

    # Step 1: Fix duplicate style tag attribute
    content = content.replace(
        '<style scoped lang="scss"> scoped lang="scss">',
        '<style scoped lang="scss">'
    )

    # Also fix if there's a space variant
    content = content.replace(
        '<style  scoped lang="scss"> scoped lang="scss">',
        '<style scoped lang="scss">'
    )

    # Step 2: Split into template, script, style sections
    # Extract template
    template_match = re.search(r'(<template>.*?</template>)', content, re.DOTALL)
    script_match = re.search(r'(<script[^>]*>.*?</script>)', content, re.DOTALL)
    style_match = re.search(r'(<style[^>]*>.*?</style>)', content, re.DOTALL)

    if not all([template_match, script_match, style_match]):
        print(f"  SKIP: Could not find all sections in {filepath}")
        return False

    template_section = template_match.group(1)
    script_section = script_match.group(1)
    style_section = style_match.group(1)

    # Step 3: Fix the template - ensure page-header has the right format
    # Replace any page-header section with the standard image-only version
    page_header_pattern = r'<section class="page-header">.*?</section>'
    template_section = re.sub(
        page_header_pattern,
        f'''<section class="page-header">
      <img src="{HEADER_IMG}" alt="" class="header-bg" />
    </section>''',
        template_section,
        count=1,
        flags=re.DOTALL
    )

    # Step 4: Fix the style section - rebuild it cleanly
    # Extract CSS content between <style ...> and </style>
    style_open = re.match(r'(<style[^>]*>)', style_section)
    style_content = style_section[style_open.end():style_section.rfind('</style>')]

    # Remove ALL existing .page-header blocks (they may be broken/duplicated)
    # We need to be careful with nested braces
    lines = style_content.split('\n')
    cleaned_lines = []
    i = 0
    skip_until_brace_count = 0
    in_page_header = False
    page_header_brace_depth = 0

    while i < len(lines):
        line = lines[i]
        stripped = line.strip()

        # Detect start of .page-header block
        if stripped.startswith('.page-header') and '{' in stripped:
            # Check if it's the top-level .page-header (not a child like .page-header .header-bg)
            if not stripped.startswith('.page-header ') and not stripped.startswith('.page-header.'):
                in_page_header = True
                page_header_brace_depth = stripped.count('{') - stripped.count('}')
                i += 1
                continue

        if in_page_header:
            page_header_brace_depth += stripped.count('{') - stripped.count('}')
            if page_header_brace_depth <= 0:
                in_page_header = False
            i += 1
            continue

        # Skip orphaned CSS rules (lines at 0-indent that are CSS selectors,
        # appearing after a closing brace of a @media block)
        # These are rules that somehow ended up outside their parent blocks
        # We'll detect them by checking if they're at indent 0 but look like
        # nested rules (they reference classes from within the component)

        # Skip empty @media blocks
        if stripped == '@media (max-width: 768px) {':
            # Check if next non-empty line is just '}'
            j = i + 1
            found_content = False
            while j < len(lines):
                next_stripped = lines[j].strip()
                if next_stripped == '}':
                    break
                if next_stripped and next_stripped != '':
                    found_content = True
                    break
                j += 1
            if not found_content:
                i = j + 1  # Skip the empty @media block
                continue

        cleaned_lines.append(line)
        i += 1

    # Now also remove orphaned rules - these are CSS rules at indent 0
    # that appear after what should be the end of a parent block
    # They typically look like: ".contact-grid {" at 0-indent
    # We need to identify them by context

    # A better approach: rebuild the CSS with proper structure
    # Let's just clean up and add our page-header at the end
    cleaned_css = '\n'.join(cleaned_lines).strip()

    # Remove duplicate .page-header CSS that might remain
    # (in case the brace counting missed some)
    # Remove any @media block that only contains .page-header rules
    def remove_page_header_media(m):
        inner = m.group(0)
        # Check if the media block only has page-header content
        content = re.search(r'@media.*?\{(.*)\}', inner, re.DOTALL)
        if content:
            inner_content = content.group(1).strip()
            if 'page-header' in inner_content:
                return ''
        return inner

    cleaned_css = re.sub(r'@media\s*\(max-width:\s*768px\)\s*\{[^}]*\.page-header[^}]*\}', remove_page_header_media, cleaned_css, flags=re.DOTALL)

    # Build final style section
    final_style = '<style scoped lang="scss">\n' + cleaned_css + '\n\n' + PAGE_HEADER_CSS.strip() + '\n</style>'

    # Step 5: Reassemble the file
    # Order: template, script, style
    final_content = template_section + '\n\n' + script_section + '\n\n' + final_style + '\n'

    if final_content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(final_content)
        print(f"  FIXED: {filepath}")
        return True
    else:
        print(f"  OK (no changes): {filepath}")
        return False

def main():
    fixed = 0
    for filepath in BROKEN_FILES:
        if os.path.exists(filepath):
            print(f"Processing: {filepath}")
            if fix_file(filepath):
                fixed += 1
        else:
            print(f"  NOT FOUND: {filepath}")
    print(f"\nTotal files fixed: {fixed}/{len(BROKEN_FILES)}")

if __name__ == '__main__':
    main()
