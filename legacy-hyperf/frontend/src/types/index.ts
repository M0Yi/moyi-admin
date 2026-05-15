// 项目相关类型
export interface Project {
  id: number
  title: string
  slug: string
  subtitle?: string
  description: string
  content?: string
  cover_image: string
  project_type?: 'medical' | 'health' | 'emergency' | 'undirected'
  project_type_label?: string
  target_amount?: number
  goal_amount?: number
  raised_amount: number
  donor_count?: number
  progress_percentage?: number
  beneficiary_count?: number
  status?: 'active' | 'completed' | 'paused'
  status_label?: string
  is_featured?: boolean
  start_date?: string
  end_date?: string
  organization?: string
  contact_phone?: string
  contact_email?: string
  created_at: string
  updated_at: string
}

export interface ProjectDetail extends Project {
  progress: ProjectProgress[]
  donations: DonationRecord[]
  related_projects: RelatedProject[]
}

export interface ProjectProgress {
  id: number
  title: string
  description: string
  images: string[]
  progress_date: string
  created_at: string
}

export interface RelatedProject {
  id: number
  title: string
  cover_image: string
  progress_percentage: number
}

// 文章相关类型
export interface Article {
  id: number
  title: string
  slug: string
  summary: string
  content?: string
  cover_image?: string
  category?: Category
  category_id?: number
  category_slug?: string
  author?: {
    name: string
    avatar?: string
  }
  published_at?: string
  published_date?: string
  view_count?: number
  is_featured?: boolean
  is_pinned?: boolean
  created_at: string
  updated_at: string
}

export interface ArticleDetail extends Article {
  attachments?: Attachment[]
  related_articles?: RelatedArticle[]
  tags?: string[]
}

export interface Category {
  id: number
  name: string
  slug: string
  description?: string
  icon?: string
  parent_id: number
  article_count?: number
  children?: Category[]
  is_single_article?: boolean
  linked_article_id?: number
}

export interface Attachment {
  name: string
  url: string
  size: number
}

export interface RelatedArticle {
  id: number
  title: string
  cover_image?: string
}

// 捐赠相关类型
export interface DonationRecord {
  id: number
  donor_name: string
  amount: number
  project?: {
    id: number
    title: string
  }
  donation_date: string
  donation_date_cn: string
  is_anonymous: boolean
}

export interface DonationDisclosureStats {
  total_donations: number
  total_amount: number
  total_donors: number
  projects_count: number
}

// 故事相关类型
export interface Story {
  id: number
  title: string
  subtitle?: string
  summary?: string
  content?: string
  story_content?: string
  cover_image?: string
  hero_image?: string
  birth_date?: string
  death_date?: string
  age?: number
  location?: string
  is_featured?: boolean
  view_count?: number
  created_at: string
  updated_at?: string
}

// 统计数据类型
export interface StatsOverview {
  historical_total: {
    amount: number
    donor_count: number
    project_count: number
  }
  current_year: {
    amount: number
    donor_count: number
    project_count: number
  }
  beneficiaries: {
    total_count: number
    current_year_count: number
  }
  online: {
    today_donations: number
    today_amount: number
  }
}

export interface RealtimeDonation {
  donor_name: string
  amount: number
  project_name: string
  donated_at: string
}

// 轮播图类型
export interface Slide {
  id: number
  title: string
  subtitle?: string
  image: string
  image_mobile?: string // 移动端图片（可选）
  link?: string
  link_url?: string
  link_text?: string
  link_type?: string
  description?: string
  is_active?: boolean
  sort_order?: number
  created_at?: string
  updated_at?: string
}

// 导航菜单类型
export interface NavigationItem {
  id: number
  name: string
  url: string
  icon?: string
  target?: string
  sort_order?: number
  children?: NavigationItem[]
}

// 分页响应类型
export interface PaginatedResponse<T> {
  items: T[]
  meta: {
    total: number
    current_page?: number
    page: number
    per_page: number
    last_page?: number
  }
}

// 通用查询参数
export interface QueryParams {
  page?: number
  per_page?: number
  page_size?: number
  search?: string
  type?: string
  status?: string
  category_id?: number
  category?: number
  category_slug?: string
}
