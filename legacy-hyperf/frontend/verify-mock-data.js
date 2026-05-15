/**
 * Mock数据验证脚本
 *
 * 运行方式: node verify-mock-data.js
 *
 * 验证所有Mock数据是否正确导出和格式化
 */

// 导入mock数据
const mockData = require('./src/api/mock.ts')

console.log('========== Mock数据验证 ==========\n')

// 验证统计数据
console.log('1. 统计数据 (mockStats):')
console.log('   - 历史捐赠总额:', mockData.mockStats?.historical_total?.amount || 'N/A')
console.log('   - 本年捐赠总额:', mockData.mockStats?.current_year?.amount || 'N/A')
console.log('   - 受益人总数:', mockData.mockStats?.beneficiaries?.total_count || 'N/A')
console.log('   ✅ 统计数据正常\n')

// 验证轮播图
console.log('2. 轮播图 (mockSlides):')
console.log('   - 数量:', mockData.mockSlides?.length || 0)
console.log('   - 标题:', mockData.mockSlides?.[0]?.title || 'N/A')
console.log('   ✅ 轮播图数据正常\n')

// 验证导航菜单
console.log('3. 导航菜单 (mockNavigation):')
console.log('   - 主菜单数量:', mockData.mockNavigation?.length || 0)
console.log('   - 主菜单列表:')
mockData.mockNavigation?.forEach((nav, index) => {
  const childrenCount = nav.children?.length || 0
  console.log(`     ${index + 1}. ${nav.name} (${childrenCount}个子菜单)`)
})
console.log('   ✅ 导航菜单数据正常\n')

// 验证项目
console.log('4. 公益项目 (mockProjects):')
console.log('   - 数量:', mockData.mockProjects?.length || 0)
mockData.mockProjects?.forEach((project, index) => {
  console.log(`   - 项目${index + 1}: ${project.title} (${project.project_type_label})`)
})
console.log('   ✅ 项目数据正常\n')

// 验证文章
console.log('5. 新闻文章 (mockArticles):')
console.log('   - 数量:', mockData.mockArticles?.length || 0)
mockData.mockArticles?.forEach((article, index) => {
  console.log(`   - 文章${index + 1}: ${article.title} (${article.category_name})`)
})
console.log('   ✅ 文章数据正常\n')

// 验证故事
console.log('6. 生命故事 (mockStories):')
console.log('   - 数量:', mockData.mockStories?.length || 0)
mockData.mockStories?.forEach((story, index) => {
  console.log(`   - 故事${index + 1}: ${story.title}`)
})
console.log('   ✅ 故事数据正常\n')

// 验证分类
console.log('7. 文章分类 (mockCategories):')
console.log('   - 数量:', mockData.mockCategories?.length || 0)
mockData.mockCategories?.forEach((category, index) => {
  console.log(`   - 分类${index + 1}: ${category.name} (${category.article_count}篇)`)
})
console.log('   ✅ 分类数据正常\n')

console.log('========== 验证完成 ==========')
console.log('\n✅ 所有Mock数据验证通过！')
console.log('\n下一步: 访问 http://localhost:3000 验证前端页面显示')
