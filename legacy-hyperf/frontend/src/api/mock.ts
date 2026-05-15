import type { Slide, NavigationItem, Article, Category, StatsOverview } from '@/types'

export const mockSlides: Slide[] = [
  {
    id: 1,
    title: '建辉慈善基金会',
    subtitle: '让行善者更有力量',
    description: '致力于发现和致敬身处困境仍坚持行善的英雄',
    image: '',
    image_mobile: '',
    link: '',
    link_text: '了解更多',
    is_active: true,
    sort_order: 1,
    created_at: '',
    updated_at: ''
  }
]

export const mockNavigation = [
  {
    id: 1,
    name: '首页',
    url: '/',
    children: [],
    sort_order: 1
  },
  {
    id: 2,
    name: '项目动态',
    url: '/articles',
    children: [
      { id: 13, name: '行善者生命故事', url: '/articles/life_story_of_good_doer', sort_order: 2 },
      { id: 14, name: '项目进展', url: '/articles/project_progress', sort_order: 3 },
      { id: 15, name: '项目效果', url: '/articles/project_effect', sort_order: 4 },
      { id: 18, name: '活动公告', url: '/articles/activity_notice', sort_order: 5 }
    ],
    sort_order: 2
  },
  {
    id: 3,
    name: '关于我们',
    url: '/about',
    children: [
      { id: 4, name: '我们是谁', url: '/about/who_we_are', sort_order: 3 },
      { id: 5, name: '基本信息', url: '/about/basic_info', sort_order: 4 },
      { id: 6, name: '使命与愿景', url: '/about/mission_vision', sort_order: 5 },
      { id: 7, name: '大事记', url: '/about/milestones', sort_order: 6 },
      { id: 8, name: '理事会', url: '/about/council', sort_order: 7 },
      { id: 9, name: '我们的团队', url: '/about/our_team', sort_order: 8 },
      { id: 11, name: '媒体报道', url: '/about/media_coverage_about', sort_order: 9 }
    ],
    sort_order: 3
  },
  {
    id: 18,
    name: '信息公开',
    url: '/disclosure',
    children: [
      { id: 19, name: '财务公开', url: '/disclosure/financial_disclosure', sort_order: 10 },
      { id: 20, name: '机构动态', url: '/disclosure/mechanism_dynamics', sort_order: 20 },
      { id: 21, name: '机构年报', url: '/disclosure/institutional_annual_report', sort_order: 30 },
      { id: 22, name: '审计报告', url: '/disclosure/audit_report', sort_order: 40 },
      { id: 23, name: '款物来源', url: '/disclosure/donation_history', sort_order: 50 },
      { id: 24, name: '款物去向', url: '/disclosure/expenditure_history', sort_order: 60 },
      { id: 25, name: '规章制度', url: '/disclosure/rules_and_regulations', sort_order: 70 },
      { id: 26, name: '致敬捐赠人', url: '/disclosure/donor', sort_order: 80 }
    ],
    sort_order: 4
  },
  {
    id: 27,
    name: '加入我们',
    url: '/join-us',
    children: [
      { id: 28, name: '我要参与', url: '/join-us/participate', sort_order: 1 },
      { id: 29, name: '我要工作', url: '/join-us/work', sort_order: 2 }
    ],
    sort_order: 5
  },
  {
    id: 30,
    name: '合作伙伴',
    url: '/partners',
    children: [],
    sort_order: 6
  }
]

// Mock articles data - 关于我们分类的文章
export const mockArticles: Article[] = [
  // 我们是谁 (category_id: 5, slug: who_we_are)
  {
    id: 1,
    title: '深圳市建辉慈善基金会简介',
    slug: 'jianhui-foundation-introduction',
    summary: '深圳市建辉慈善基金会是一家致力于发现和支持身处困境但仍坚持行善的普通人的公益组织。',
    content: '<h2>机构概况</h2><p>深圳市建辉慈善基金会（以下简称"建辉基金会"）成立于2016年，是一家在深圳市民政局注册成立的非公募基金会。</p><h2>我们的使命</h2><p>建辉基金会的使命是"让行善者更有力量"。我们致力于发现那些身处困境但仍坚持行善的普通人，通过资金支持、媒体宣传和社会倡导等方式，为他们提供持续的帮助和关注。</p><h2>核心价值观</h2><ul><li>尊重每一位行善者的尊严和价值</li><li>透明、公正地使用每一笔善款</li><li>用专业的方法提供有效的帮助</li><li>倡导全社会关注和支持行善者</li></ul>',
    cover_image: '',
    published_date: '2024-01-01',
    view_count: 1520,
    category_id: 5,
    category_slug: 'who_we_are',
    is_featured: false,
    is_pinned: false,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z'
  },
  // 基本信息 (category_id: 6, slug: basic_info)
  {
    id: 2,
    title: '基金会基本信息',
    slug: 'foundation-basic-info',
    summary: '建辉慈善基金会的登记信息、宗旨、业务范围等基本信息。',
    content: '<h2>登记信息</h2><p>名称：深圳市建辉慈善基金会</p><p>统一社会信用代码：53440300MJL0167883</p><p>登记管理机关：深圳市民政局</p><h2>宗旨</h2><p>遵守宪法、法律、法规和国家政策，遵守社会道德风尚，弘扬慈善精神，救助社会困难群体，促进社会和谐进步。</p>',
    cover_image: '',
    published_date: '2024-01-02',
    view_count: 890,
    category_id: 6,
    category_slug: 'basic_info',
    is_featured: false,
    is_pinned: false,
    created_at: '2024-01-02T00:00:00Z',
    updated_at: '2024-01-02T00:00:00Z'
  },
  // 使命与愿景 (category_id: 7, slug: mission_vision)
  {
    id: 3,
    title: '我们的使命与愿景',
    slug: 'mission-and-vision',
    summary: '让行善者更有力量，成为最受信赖的行善者支持平台。',
    content: '<h2>使命</h2><p><strong>让行善者更有力量</strong></p><p>我们相信，每一个行善者都是社会的一盏灯。当他们在困境中依然坚持行善时，理应得到社会的关注、尊重和支持。</p><h2>愿景</h2><p><strong>成为最受信赖的行善者支持平台</strong></p><p>我们希望建立一个透明、高效、可持续的公益平台，连接社会爱心资源与困境中的行善者。</p>',
    cover_image: '',
    published_date: '2024-01-03',
    view_count: 1234,
    category_id: 7,
    category_slug: 'mission_vision',
    is_featured: true,
    is_pinned: false,
    created_at: '2024-01-03T00:00:00Z',
    updated_at: '2024-01-03T00:00:00Z'
  },
  // 大事记 (category_id: 8, slug: milestones)
  {
    id: 4,
    title: '建辉基金会发展历程',
    slug: 'foundation-milestones',
    summary: '回顾建辉基金会成立以来的重要发展节点。',
    content: '<h2>2016年</h2><ul><li>深圳市建辉慈善基金会在深圳市民政局正式注册成立</li><li>确立"致敬困境中的行善者"为核心使命</li></ul><h2>2017年</h2><ul><li>首批受助行善者名单确定，开始提供持续支持</li><li>建立行善者发现和评估体系</li></ul><h2>2018年</h2><ul><li>开发"行善者故事"栏目，通过媒体传播行善者事迹</li></ul><h2>至今</h2><ul><li>持续发现和支持更多困境中的行善者</li></ul>',
    cover_image: '',
    published_date: '2024-01-04',
    view_count: 756,
    category_id: 8,
    category_slug: 'milestones',
    is_featured: false,
    is_pinned: false,
    created_at: '2024-01-04T00:00:00Z',
    updated_at: '2024-01-04T00:00:00Z'
  },
  // 理事会 (category_id: 9, slug: council)
  {
    id: 5,
    title: '理事会成员介绍',
    slug: 'council-members',
    summary: '建辉基金会理事会由一群热心公益、经验丰富的专业人士组成。',
    content: '<h2>理事会</h2><p>深圳市建辉慈善基金会理事会是基金会的决策机构，负责制定基金会的发展战略、审议重大事项、监督基金会的运营管理。</p><h2>理事会职责</h2><ul><li>制定和修改基金会章程</li><li>审议基金会年度工作计划和预算</li><li>审议基金会年度工作报告和财务报告</li></ul>',
    cover_image: '',
    published_date: '2024-01-05',
    view_count: 543,
    category_id: 9,
    category_slug: 'council',
    is_featured: false,
    is_pinned: false,
    created_at: '2024-01-05T00:00:00Z',
    updated_at: '2024-01-05T00:00:00Z'
  },
  // 我们的团队 (category_id: 10, slug: our_team)
  {
    id: 6,
    title: '我们的团队',
    slug: 'our-team',
    summary: '建辉基金会拥有一支专业、热情、富有爱心的团队。',
    content: '<h2>团队介绍</h2><p>深圳市建辉慈善基金会的团队由一群热爱公益事业、充满理想和热情的专业人士组成。我们来自不同的背景，但有着共同的使命——让行善者更有力量。</p><h2>团队文化</h2><p><strong>以使命为导向</strong></p><p>我们始终牢记"让行善者更有力量"的使命，将行善者的需求放在第一位。</p><p><strong>专业高效</strong></p><p>我们以专业的态度和方法开展工作，追求高效的项目执行和资金使用。</p>',
    cover_image: '',
    published_date: '2024-01-06',
    view_count: 432,
    category_id: 10,
    category_slug: 'our_team',
    is_featured: false,
    is_pinned: false,
    created_at: '2024-01-06T00:00:00Z',
    updated_at: '2024-01-06T00:00:00Z'
  },
  // 媒体报道 (category_id: 11, slug: media_coverage_about)
  {
    id: 7,
    title: '建辉基金会获媒体广泛关注',
    slug: 'media-coverage-highlights',
    summary: '建辉基金会的公益实践获得社会各界和媒体的广泛关注和认可。',
    content: '<h2>媒体关注</h2><p>深圳市建辉基金会在致敬困境中的行善者方面的实践和探索，获得了社会各界的广泛关注和认可。</p><h2>报道亮点</h2><p>多家主流媒体对建辉基金会及其支持的行善者进行了深入报道，包括：</p><ul><li>《人民日报》报道了建辉基金会支持的行善者感人故事</li><li>《南方日报》专题报道"致敬困境中的行善者"项目</li></ul>',
    cover_image: '',
    published_date: '2024-01-07',
    view_count: 678,
    category_id: 11,
    category_slug: 'media_coverage_about',
    is_featured: true,
    is_pinned: false,
    created_at: '2024-01-07T00:00:00Z',
    updated_at: '2024-01-07T00:00:00Z'
  },
  // 生命故事 - 用于首页轮播（type: story）
  {
    id: 101,
    title: '张阿姨的十年助学路',
    slug: 'zhang-aunt-ten-years-education',
    summary: '退休教师张阿姨，用退休金资助了50多名贫困学生，十年如一日坚持助学。',
    content: '<h2>行善者简介</h2><p>张阿姨，68岁，退休教师，住在老旧的小区里，每月退休金只有3000多元。</p><h2>行善事迹</h2><p>从2014年开始，张阿姨开始用自己的退休金资助贫困学生。十年来，她累计资助了50多名学生，捐款超过20万元。</p><p>她自己生活节俭，穿着朴素，但对孩子们从不吝啬。</p>',
    cover_image: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&h=200&fit=crop',
    published_date: '2024-03-01',
    view_count: 2341,
    category_id: 101,
    category_slug: 'inspirational',
    is_featured: true,
    is_pinned: true,
    created_at: '2024-03-01T00:00:00Z',
    updated_at: '2024-03-01T00:00:00Z'
  },
  {
    id: 102,
    title: '李师傅的免费修车铺',
    slug: 'li-master-free-repair',
    summary: '残疾人李师傅在社区开了间免费修车铺，为老人和残疾人免费修理自行车、轮椅。',
    content: '<h2>行善者简介</h2><p>李师傅，55岁，腿部残疾，靠修车手艺为生。</p><h2>行善事迹</h2><p>李师傅在自己经营的修车铺里，专门为60岁以上老人和残疾人提供免费修车服务。每天至少服务3-5人，坚持了8年。</p>',
    cover_image: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop',
    published_date: '2024-03-05',
    view_count: 1876,
    category_id: 101,
    category_slug: 'inspirational',
    is_featured: true,
    is_pinned: false,
    created_at: '2024-03-05T00:00:00Z',
    updated_at: '2024-03-05T00:00:00Z'
  },
  {
    id: 103,
    title: '王奶奶的爱心午餐',
    slug: 'wang-grandma-love-lunch',
    summary: '独居老人王奶奶每天为社区里的孤寡老人做爱心午餐，五年送餐超过5000份。',
    content: '<h2>行善者简介</h2><p>王奶奶，72岁，独居老人，自己身体也不太好。</p><h2>行善事迹</h2><p>五年前，王奶奶发现社区里有不少孤寡老人吃饭困难，她开始每天多做一些饭菜，送给需要的老人。</p><p>不管刮风下雨，王奶奶的爱心午餐从未间断。</p>',
    cover_image: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=200&h=200&fit=crop',
    published_date: '2024-03-10',
    view_count: 3156,
    category_id: 102,
    category_slug: 'kindness',
    is_featured: true,
    is_pinned: true,
    created_at: '2024-03-10T00:00:00Z',
    updated_at: '2024-03-10T00:00:00Z'
  }
]

// Mock categories data
export const mockCategories: Category[] = [
  // 项目动态
  {
    id: 1,
    name: '项目动态',
    slug: 'project_introduction',
    description: '建辉基金会各类公益项目的最新进展',
    parent_id: 0,
    article_count: 10,
    children: [
      {
        id: 2,
        name: '项目介绍',
        slug: 'project_info',
        description: '详细介绍我们的公益项目',
        parent_id: 1,
        article_count: 5
      },
      {
        id: 3,
        name: '行善者生命故事',
        slug: 'life_story_of_good_doer',
        description: '记录行善者的感人事迹',
        parent_id: 1,
        article_count: 8
      }
    ]
  },
  // 关于我们
  {
    id: 4,
    name: '关于我们',
    slug: 'about_us',
    description: '了解建辉慈善基金会',
    parent_id: 0,
    article_count: 7,
    children: [
      {
        id: 5,
        name: '我们是谁',
        slug: 'who_we_are',
        description: '深圳市建辉慈善基金会简介',
        parent_id: 4,
        article_count: 1
      },
      {
        id: 6,
        name: '基本信息',
        slug: 'basic_info',
        description: '基金会基本信息',
        parent_id: 4,
        article_count: 1
      },
      {
        id: 7,
        name: '使命与愿景',
        slug: 'mission_vision',
        description: '让行善者更有力量',
        parent_id: 4,
        article_count: 1
      },
      {
        id: 8,
        name: '大事记',
        slug: 'milestones',
        description: '建辉基金会发展历程',
        parent_id: 4,
        article_count: 1
      },
      {
        id: 9,
        name: '理事会',
        slug: 'council',
        description: '理事会成员介绍',
        parent_id: 4,
        article_count: 1
      },
      {
        id: 10,
        name: '我们的团队',
        slug: 'our_team',
        description: '建辉基金会团队介绍',
        parent_id: 4,
        article_count: 1
      },
      {
        id: 11,
        name: '媒体报道',
        slug: 'media_coverage_about',
        description: '建辉基金会媒体报道',
        parent_id: 4,
        article_count: 1
      }
    ]
  }
]

// Mock stats data
export const mockStats: StatsOverview = {
  historical_total: {
    amount: 1583775789.69,
    donor_count: 32580,
    project_count: 156
  },
  current_year: {
    amount: 1234567.89,
    donor_count: 1523,
    project_count: 42
  },
  beneficiaries: {
    total_count: 8956,
    current_year_count: 1234
  },
  online: {
    today_donations: 45,
    today_amount: 12345.67
  }
}

export const mockProjects = [
  {
    id: 1,
    title: '致敬困境中的行善者',
    slug: 'respect-heroes',
    subtitle: '为行善者提供关怀',
    description: '为那些身处困境但依然坚持行善的人们提供关怀和支持',
    cover_image: '',
    goal_amount: 1000000,
    raised_amount: 500000,
    donor_count: 1523,
    status: 'active',
    start_date: '2024-01-01',
    end_date: '2024-12-31',
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z'
  },
  {
    id: 2,
    title: '生命故事计划',
    slug: 'life-stories',
    subtitle: '记录平凡人的不平凡故事',
    description: '发现并记录社会中那些默默奉献的普通人的感人故事',
    cover_image: '',
    goal_amount: 500000,
    raised_amount: 250000,
    donor_count: 856,
    status: 'active',
    start_date: '2024-01-01',
    end_date: '2024-12-31',
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z'
  },
  {
    id: 3,
    title: '应急救助基金',
    slug: 'emergency-relief',
    subtitle: '为突发情况提供及时帮助',
    description: '为遭遇突发事件或陷入困境的行善者提供紧急救助',
    cover_image: '',
    goal_amount: 2000000,
    raised_amount: 800000,
    donor_count: 2341,
    status: 'active',
    start_date: '2024-01-01',
    end_date: '2024-12-31',
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z'
  }
]

// Mock stories data
export const mockStories = [
  {
    id: 1,
    title: '致敬困境中的行善者',
    subtitle: '为行善者提供关怀',
    summary: '为那些身处困境但依然坚持行善的人们提供关怀和支持',
    cover_image: '',
    view_count: 1520,
    is_featured: true,
    created_at: '2024-01-01T00:00:00Z'
  },
  {
    id: 2,
    title: '生命故事计划',
    subtitle: '记录平凡人的不平凡故事',
    summary: '发现并记录社会中那些默默奉献的普通人的感人故事',
    cover_image: '',
    view_count: 856,
    is_featured: true,
    created_at: '2024-01-02T00:00:00Z'
  }
]
