import { ref, onMounted, onUnmounted } from 'vue'

/**
 * 滚动驱动动画 Composable
 * 支持：视差、粘性滚动、滚动关联动画
 */
export function useScrollAnimations() {
  const scrollProgress = ref(0)
  const activeSection = ref('')

  // 视差效果
  const initParallax = () => {
    const parallaxElements = document.querySelectorAll('[data-parallax]')

    const handleParallax = () => {
      const windowHeight = window.innerHeight

      parallaxElements.forEach((el: Element) => {
        const htmlEl = el as HTMLElement
        const rect = htmlEl.getBoundingClientRect()
        const speed = parseFloat(htmlEl.dataset.parallax || '0.5')

        if (rect.top < windowHeight && rect.bottom > 0) {
          const translateY = (window.scrollY * speed) % windowHeight
          htmlEl.style.transform = `translateY(${translateY}px)`
        }
      })
    }

    window.addEventListener('scroll', handleParallax, { passive: true })
    return () => window.removeEventListener('scroll', handleParallax)
  }

  // 更新滚动位置
  const updateScroll = () => {
    const docHeight = document.documentElement.scrollHeight - window.innerHeight
    scrollProgress.value = window.scrollY / docHeight

    // 检测是否滚过hero section，控制stats-card的淡出
    updateStatsVisibility()
  }

  // 更新统计卡片的可见性
  const updateStatsVisibility = () => {
    const statsOverlay = document.querySelector('.stats-overlay') as HTMLElement | null
    const carouselContainer = document.querySelector('.carousel-container') as HTMLElement | null
    const heroSection = document.querySelector('.hero-section') as HTMLElement | null

    if (!statsOverlay || !heroSection) return

    const heroRect = heroSection.getBoundingClientRect()
    const viewportHeight = window.innerHeight

    // 计算hero section底部的位置比例（0 = 完全离开视口，1 = 在视口底部）
    const heroBottomRatio = Math.max(0, Math.min(1, heroRect.bottom / viewportHeight))

    // 当hero底部在视口顶部以下时才应用淡出效果
    // 如果hero完全在视口内（>= 0.8），保持完全可见
    if (heroBottomRatio >= 0.8) {
      // 完全可见 - 清除inline style，让CSS控制
      statsOverlay.style.opacity = ''
      statsOverlay.style.transform = ''

      // 轮播图也保持完全可见
      if (carouselContainer) {
        carouselContainer.style.opacity = ''
        carouselContainer.style.transform = ''
      }
    } else if (heroBottomRatio > 0 && heroBottomRatio < 0.8) {
      // 计算淡出进度 (0 = 开始淡出, 1 = 完全隐藏)
      const fadeProgress = 1 - (heroBottomRatio / 0.8)
      const clampedProgress = Math.max(0, Math.min(1, fadeProgress))

      // 应用透明度和上移效果 - stats-card
      statsOverlay.style.opacity = (1 - clampedProgress * 0.9).toString() // 最多淡出到0.1
      statsOverlay.style.transform = `translateY(-${clampedProgress * 100}px)` // 向上移动100px

      // 轮播图也向上淡出
      if (carouselContainer) {
        carouselContainer.style.opacity = (1 - clampedProgress * 0.7).toString() // 最多淡出到0.3
        carouselContainer.style.transform = `translateY(-${clampedProgress * 50}px)` // 向上移动50px
      }
    } else {
      // 完全隐藏（hero底部离开视口）
      statsOverlay.style.opacity = '0'
      statsOverlay.style.transform = 'translateY(-100px)'

      // 轮播图也完全隐藏
      if (carouselContainer) {
        carouselContainer.style.opacity = '0'
        carouselContainer.style.transform = 'translateY(-50px)'
      }
    }
  }

  // Intersection Observer 用于检测元素进入视口
  const observeElements = () => {
    const observerOptions = {
      root: null,
      rootMargin: '-10% 0px -10% 0px', // 当元素在视口中间时触发
      threshold: [0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1]
    }

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        const target = entry.target as HTMLElement

        if (entry.isIntersecting) {
          // 计算进入视口的进度
          const progress = entry.intersectionRatio
          target.style.setProperty('--scroll-progress', progress.toString())

          // 添加动画类
          if (progress > 0.1) {
            target.classList.add('is-visible')
          }
          if (progress > 0.5) {
            target.classList.add('is-in-view')
          }
        } else {
          target.classList.remove('is-visible', 'is-in-view')
        }
      })
    }, observerOptions)

    // 观察所有带有 scroll-animate 类的元素
    document.querySelectorAll('.scroll-animate').forEach(el => {
      observer.observe(el)
    })
  }

  // 初始化滚动吸附
  const initScrollSnap = () => {
    const container = document.querySelector('.home-page')
    if (container) {
      // 为每个section添加吸附点
      container.style.scrollSnapType = 'y proximity'
      container.style.scrollBehavior = 'smooth'
    }
  }

  // 粘性元素观察器
  const initStickyElements = () => {
    const stickyElements = document.querySelectorAll('[data-sticky]')

    stickyElements.forEach((el: Element) => {
      const htmlEl = el as HTMLElement
      const observer = new IntersectionObserver(
        ([e]) => {
          if (e.isIntersecting) {
            htmlEl.classList.add('is-stuck')
          } else {
            htmlEl.classList.remove('is-stuck')
          }
        },
        { threshold: [0, 1] }
      )
      observer.observe(htmlEl)
    })
  }

  let cleanupParallax: (() => void) | undefined
  let cleanupScroll: (() => void) | undefined

  onMounted(() => {
    // 初始化各种动画效果
    observeElements()
    cleanupParallax = initParallax()
    initScrollSnap()
    initStickyElements()

    // 监听滚动
    const handleScroll = () => {
      requestAnimationFrame(updateScroll)
    }

    window.addEventListener('scroll', handleScroll, { passive: true })
    cleanupScroll = () => window.removeEventListener('scroll', handleScroll)

    // 初始更新
    updateScroll()
  })

  onUnmounted(() => {
    if (cleanupParallax) cleanupParallax()
    if (cleanupScroll) cleanupScroll()
  })

  return {
    scrollProgress,
    activeSection
  }
}
