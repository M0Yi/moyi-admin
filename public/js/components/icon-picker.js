/**
 * 图标选择器组件 JavaScript
 * 
 * 使用说明：
 * 1. 确保已引入 Bootstrap 5 和 Bootstrap Icons
 * 2. 在页面中引入此文件：<script src="/js/components/icon-picker.js"></script>
 * 3. 使用方式：在按钮上添加 data-bs-toggle="modal" data-bs-target="#iconPickerModal" data-target-input="输入框ID"
 */

// Bootstrap Icons 图标列表（完整列表）
// 注意：请手动将完整的图标列表复制到这里
// 从原 blade 文件中复制 bootstrapIcons 数组的内容
const bootstrapIcons = [
    '0-circle-fill','filetype-mp4','0-circle','filetype-otf','0-square-fill','filetype-pdf','0-square','filetype-php','1-circle-fill','filetype-png','1-circle','filetype-ppt','1-square-fill','filetype-pptx','1-square','filetype-psd','123','filetype-py','2-circle-fill','filetype-raw','2-circle','filetype-rb','2-square-fill','filetype-sass','2-square','filetype-scss','3-circle-fill','filetype-sh','3-circle','filetype-sql','3-square-fill','filetype-svg','3-square','filetype-tiff','4-circle-fill','filetype-tsx','4-circle','filetype-ttf','4-square-fill','filetype-txt','4-square','filetype-wav','5-circle-fill','filetype-woff','5-circle','filetype-xls','5-square-fill','filetype-xlsx','5-square','filetype-xml','6-circle-fill','filetype-yml','6-circle','film','6-square-fill','filter-circle-fill','6-square','filter-circle','7-circle-fill','filter-left','7-circle','filter-right','7-square-fill','filter-square-fill','7-square','filter-square','8-circle-fill','filter','8-circle','fingerprint','8-square-fill','fire','8-square','flag-fill','9-circle-fill','flag','9-circle','flask-fill','9-square-fill','flask-florence-fill','9-square','flask-florence','activity','flask','airplane-engines-fill','floppy-fill','airplane-engines','floppy','airplane-fill','floppy2-fill','airplane','floppy2','alarm-fill','flower1','alarm','flower2','alexa','flower3','align-bottom','folder-check','align-center','folder-fill','align-end','folder-minus','align-middle','folder-plus','align-start','folder-symlink-fill','align-top','folder-symlink','alipay','folder-x','alphabet-uppercase','folder','alphabet','folder2-open','alt','folder2','amazon','fonts','amd','fork-knife','android','forward-fill','android2','forward','anthropic','front','app-indicator','fuel-pump-diesel-fill','app','fuel-pump-diesel','apple-music','fuel-pump-fill','apple','fuel-pump','archive-fill','fullscreen-exit','archive','fullscreen','arrow-90deg-down','funnel-fill','arrow-90deg-left','funnel','arrow-90deg-right','gear-fill','arrow-90deg-up','gear-wide-connected','arrow-bar-down','gear-wide','arrow-bar-left','gear','arrow-bar-right','gem','arrow-bar-up','gender-ambiguous','arrow-clockwise','gender-female','arrow-counterclockwise','gender-male','arrow-down-circle-fill','gender-neuter','arrow-down-circle','gender-trans','arrow-down-left-circle-fill','geo-alt-fill','arrow-down-left-circle','geo-alt','arrow-down-left-square-fill','geo-fill','arrow-down-left-square','geo','arrow-down-left','gift-fill','arrow-down-right-circle-fill','gift','arrow-down-right-circle','git','arrow-down-right-square-fill','github','arrow-down-right-square','gitlab','arrow-down-right','globe-americas-fill','arrow-down-short','globe-americas','arrow-down-square-fill','globe-asia-australia-fill','arrow-down-square','globe-asia-australia','arrow-down-up','globe-central-south-asia-fill','arrow-down','globe-central-south-asia','arrow-left-circle-fill','globe-europe-africa-fill','arrow-left-circle','globe-europe-africa','arrow-left-right','globe','arrow-left-short','globe2','arrow-left-square-fill','google-play','arrow-left-square','google','arrow-left','gpu-card','arrow-repeat','graph-down-arrow','arrow-return-left','graph-down','arrow-return-right','graph-up-arrow','arrow-right-circle-fill','graph-up','arrow-right-circle','grid-1x2-fill','arrow-right-short','grid-1x2','arrow-right-square-fill','grid-3x2-gap-fill','arrow-right-square','grid-3x2-gap','arrow-right','grid-3x2','arrow-through-heart-fill','grid-3x3-gap-fill','arrow-through-heart','grid-3x3-gap','arrow-up-circle-fill','grid-3x3','arrow-up-circle','grid-fill','arrow-up-left-circle-fill','grid','arrow-up-left-circle','grip-horizontal','arrow-up-left-square-fill','grip-vertical','arrow-up-left-square','h-circle-fill','arrow-up-left','h-circle','arrow-up-right-circle-fill','h-square-fill','arrow-up-right-circle','h-square','arrow-up-right-square-fill','hammer','arrow-up-right-square','hand-index-fill','arrow-up-right','hand-index-thumb-fill','arrow-up-short','hand-index-thumb','arrow-up-square-fill','hand-index','arrow-up-square','hand-thumbs-down-fill','arrow-up','hand-thumbs-down','arrows-angle-contract','hand-thumbs-up-fill','arrows-angle-expand','hand-thumbs-up','arrows-collapse-vertical','handbag-fill','arrows-collapse','handbag','arrows-expand-vertical','hash','arrows-expand','hdd-fill','arrows-fullscreen','hdd-network-fill','arrows-move','hdd-network','arrows-vertical','hdd-rack-fill','arrows','hdd-rack','aspect-ratio-fill','hdd-stack-fill','aspect-ratio','hdd-stack','asterisk','hdd','at','hdmi-fill','award-fill','hdmi','award','headphones','back','headset-vr','backpack-fill','headset','backpack','heart-arrow','backpack2-fill','heart-fill','backpack2','heart-half','backpack3-fill','heart-pulse-fill','backpack3','heart-pulse','backpack4-fill','heart','backpack4','heartbreak-fill','backspace-fill','heartbreak','backspace-reverse-fill','hearts','backspace-reverse','heptagon-fill','backspace','heptagon-half','badge-3d-fill','heptagon','badge-3d','hexagon-fill','badge-4k-fill','hexagon-half','badge-4k','hexagon','badge-8k-fill','highlighter','badge-8k','highlights','badge-ad-fill','hospital-fill','badge-ad','hospital','badge-ar-fill','hourglass-bottom','badge-ar','hourglass-split','badge-cc-fill','hourglass-top','badge-cc','hourglass','badge-hd-fill','house-add-fill','badge-hd','house-add','badge-sd-fill','house-check-fill','badge-sd','house-check','badge-tm-fill','house-dash-fill','badge-tm','house-dash','badge-vo-fill','house-door-fill','badge-vo','house-door','badge-vr-fill','house-down-fill','badge-vr','house-down','badge-wc-fill','house-exclamation-fill','badge-wc','house-exclamation','bag-check-fill','house-fill','bag-check','house-gear-fill','bag-dash-fill','house-gear','bag-dash','house-heart-fill','bag-fill','house-heart','bag-heart-fill','house-lock-fill','bag-heart','house-lock','bag-plus-fill','house-slash-fill','bag-plus','house-slash','bag-x-fill','house-up-fill','bag-x','house-up','bag','house-x-fill','balloon-fill','house-x','balloon-heart-fill','house','balloon-heart','houses-fill','balloon','houses','ban-fill','hr','ban','hurricane','bandaid-fill','hypnotize','bandaid','image-alt','bank','image-fill','bank2','image','bar-chart-fill','images','bar-chart-line-fill','inbox-fill','bar-chart-line','inbox','bar-chart-steps','inboxes-fill','bar-chart','inboxes','basket-fill','incognito','basket','indent','basket2-fill','infinity','basket2','info-circle-fill','basket3-fill','info-circle','basket3','info-lg','battery-charging','info-square-fill','battery-full','info-square','battery-half','info','battery-low','input-cursor-text','battery','input-cursor','beaker-fill','instagram','beaker','intersect','behance','javascript','bell-fill','journal-album','bell-slash-fill','journal-arrow-down','bell-slash','journal-arrow-up','bell','journal-bookmark-fill','bezier','journal-bookmark','bezier2','journal-check','bicycle','journal-code','bing','journal-medical','binoculars-fill','journal-minus','binoculars','journal-plus','blockquote-left','journal-richtext','blockquote-right','journal-text','bluesky','journal-x','bluetooth','journal','body-text','journals','book-fill','joystick','book-half','justify-left','book','justify-right','bookmark-check-fill','justify','bookmark-check','kanban-fill','bookmark-dash-fill','kanban','bookmark-dash','key-fill','bookmark-fill','key','bookmark-heart-fill','keyboard-fill','bookmark-heart','keyboard','bookmark-plus-fill','ladder','bookmark-plus','lamp-fill','bookmark-star-fill','lamp','bookmark-star','laptop-fill','bookmark-x-fill','laptop','bookmark-x','layer-backward','bookmark','layer-forward','bookmarks-fill','layers-fill','bookmarks','layers-half','bookshelf','layers','boombox-fill','layout-sidebar-inset-reverse','boombox','layout-sidebar-inset','bootstrap-fill','layout-sidebar-reverse','bootstrap-icons','layout-sidebar','bootstrap-reboot','layout-split','bootstrap','layout-text-sidebar-reverse','border-all','layout-text-sidebar','border-bottom','layout-text-window-reverse','border-center','layout-text-window','border-inner','layout-three-columns','border-left','layout-wtf','border-middle','leaf-fill','border-outer','leaf','border-right','life-preserver','border-style','lightbulb-fill','border-top','lightbulb-off-fill','border-width','lightbulb-off','border','lightbulb','bounding-box-circles','lightning-charge-fill','bounding-box','lightning-charge','box-arrow-down-left','lightning-fill','box-arrow-down-right','lightning','box-arrow-down','line','box-arrow-in-down-left','link-45deg','box-arrow-in-down-right','link','box-arrow-in-down','linkedin','box-arrow-in-left','list-check','box-arrow-in-right','list-columns-reverse','box-arrow-in-up-left','list-columns','box-arrow-in-up-right','list-nested','box-arrow-in-up','list-ol','box-arrow-left','list-stars','box-arrow-right','list-task','box-arrow-up-left','list-ul','box-arrow-up-right','list','box-arrow-up','lock-fill','box-fill','lock','box-seam-fill','luggage-fill','box-seam','luggage','box','lungs-fill','box2-fill','lungs','box2-heart-fill','magic','box2-heart','magnet-fill','box2','magnet','boxes','mailbox-flag','braces-asterisk','mailbox','braces','mailbox2-flag','bricks','mailbox2','briefcase-fill','map-fill','briefcase','map','brightness-alt-high-fill','markdown-fill','brightness-alt-high','markdown','brightness-alt-low-fill','marker-tip','brightness-alt-low','mask','brightness-high-fill','mastodon','brightness-high','measuring-cup-fill','brightness-low-fill','measuring-cup','brightness-low','medium','brilliance','megaphone-fill','broadcast-pin','megaphone','broadcast','memory','browser-chrome','menu-app-fill','browser-edge','menu-app','browser-firefox','menu-button-fill','browser-safari','menu-button-wide-fill','brush-fill','menu-button-wide','brush','menu-button','bucket-fill','menu-down','bucket','menu-up','bug-fill','messenger','bug','meta','building-add','mic-fill','building-check','mic-mute-fill','building-dash','mic-mute','building-down','mic','building-exclamation','microsoft-teams','building-fill-add','microsoft','building-fill-check','minecart-loaded','building-fill-dash','minecart','building-fill-down','modem-fill','building-fill-exclamation','modem','building-fill-gear','moisture','building-fill-lock','moon-fill','building-fill-slash','moon-stars-fill','building-fill-up','moon-stars','building-fill-x','moon','building-fill','mortarboard-fill','building-gear','mortarboard','building-lock','motherboard-fill','building-slash','motherboard','building-up','mouse-fill','building-x','mouse','building','mouse2-fill','buildings-fill','mouse2','buildings','mouse3-fill','bullseye','mouse3','bus-front-fill','music-note-beamed','bus-front','music-note-list','c-circle-fill','music-note','c-circle','music-player-fill','c-square-fill','music-player','c-square','newspaper','cake-fill','nintendo-switch','cake','node-minus-fill','cake2-fill','node-minus','cake2','node-plus-fill','calculator-fill','node-plus','calculator','noise-reduction','calendar-check-fill','nut-fill','calendar-check','nut','calendar-date-fill','nvidia','calendar-date','nvme-fill','calendar-day-fill','nvme','calendar-day','octagon-fill','calendar-event-fill','octagon-half','calendar-event','octagon','calendar-fill','openai','calendar-heart-fill','opencollective','calendar-heart','optical-audio-fill','calendar-minus-fill','optical-audio','calendar-minus','option','calendar-month-fill','outlet','calendar-month','p-circle-fill','calendar-plus-fill','p-circle','calendar-plus','p-square-fill','calendar-range-fill','p-square','calendar-range','paint-bucket','calendar-week-fill','palette-fill','calendar-week','palette','calendar-x-fill','palette2','calendar-x','paperclip','calendar','paragraph','calendar2-check-fill','pass-fill','calendar2-check','pass','calendar2-date-fill','passport-fill','calendar2-date','passport','calendar2-day-fill','patch-check-fill','calendar2-day','patch-check','calendar2-event-fill','patch-exclamation-fill','calendar2-event','patch-exclamation','calendar2-fill','patch-minus-fill','calendar2-heart-fill','patch-minus','calendar2-heart','patch-plus-fill','calendar2-minus-fill','patch-plus','calendar2-minus','patch-question-fill','calendar2-month-fill','patch-question','calendar2-month','pause-btn-fill','calendar2-plus-fill','pause-btn','calendar2-plus','pause-circle-fill','calendar2-range-fill','pause-circle','calendar2-range','pause-fill','calendar2-week-fill','pause','calendar2-week','paypal','calendar2-x-fill','pc-display-horizontal','calendar2-x','pc-display','calendar2','pc-horizontal','calendar3-event-fill','pc','calendar3-event','pci-card-network','calendar3-fill','pci-card-sound','calendar3-range-fill','pci-card','calendar3-range','peace-fill','calendar3-week-fill','peace','calendar3-week','pen-fill','calendar3','pen','calendar4-event','pencil-fill','calendar4-range','pencil-square','calendar4-week','pencil','calendar4','pentagon-fill','camera-fill','pentagon-half','camera-reels-fill','pentagon','camera-reels','people-fill','camera-video-fill','people','camera-video-off-fill','percent','camera-video-off','perplexity','camera-video','person-add','camera','person-arms-up','camera2','person-badge-fill','capslock-fill','person-badge','capslock','person-bounding-box','capsule-pill','person-check-fill','capsule','person-check','car-front-fill','person-circle','car-front','person-dash-fill','card-checklist','person-dash','card-heading','person-down','card-image','person-exclamation','card-list','person-fill-add','card-text','person-fill-check','caret-down-fill','person-fill-dash','caret-down-square-fill','person-fill-down','caret-down-square','person-fill-exclamation','caret-down','person-fill-gear','caret-left-fill','person-fill-lock','caret-left-square-fill','person-fill-slash','caret-left-square','person-fill-up','caret-left','person-fill-x','caret-right-fill','person-fill','caret-right-square-fill','person-gear','caret-right-square','person-heart','caret-right','person-hearts','caret-up-fill','person-lines-fill','caret-up-square-fill','person-lock','caret-up-square','person-plus-fill','caret-up','person-plus','cart-check-fill','person-raised-hand','cart-check','person-rolodex','cart-dash-fill','person-slash','cart-dash','person-square','cart-fill','person-standing-dress','cart-plus-fill','person-standing','cart-plus','person-up','cart-x-fill','person-vcard-fill','cart-x','person-vcard','cart','person-video','cart2','person-video2','cart3','person-video3','cart4','person-walking','cash-coin','person-wheelchair','cash-stack','person-workspace','cash','person-x-fill','cassette-fill','person-x','cassette','person','cast','phone-fill','cc-circle-fill','phone-flip','cc-circle','phone-landscape-fill','cc-square-fill','phone-landscape','cc-square','phone-vibrate-fill','chat-dots-fill','phone-vibrate','chat-dots','phone','chat-fill','pie-chart-fill','chat-heart-fill','pie-chart','chat-heart','piggy-bank-fill','chat-left-dots-fill','piggy-bank','chat-left-dots','pin-angle-fill','chat-left-fill','pin-angle','chat-left-heart-fill','pin-fill','chat-left-heart','pin-map-fill','chat-left-quote-fill','pin-map','chat-left-quote','pin','chat-left-text-fill','pinterest','chat-left-text','pip-fill','chat-left','pip','chat-quote-fill','play-btn-fill','chat-quote','play-btn','chat-right-dots-fill','play-circle-fill','chat-right-dots','play-circle','chat-right-fill','play-fill','chat-right-heart-fill','play','chat-right-heart','playstation','chat-right-quote-fill','plug-fill','chat-right-quote','plug','chat-right-text-fill','plugin','chat-right-text','plus-circle-dotted','chat-right','plus-circle-fill','chat-square-dots-fill','plus-circle','chat-square-dots','plus-lg','chat-square-fill','plus-slash-minus','chat-square-heart-fill','plus-square-dotted','chat-square-heart','plus-square-fill','chat-square-quote-fill','plus-square','chat-square-quote','plus','chat-square-text-fill','postage-fill','chat-square-text','postage-heart-fill','chat-square','postage-heart','chat-text-fill','postage','chat-text','postcard-fill','chat','postcard-heart-fill','check-all','postcard-heart','check-circle-fill','postcard','check-circle','power','check-lg','prescription','check-square-fill','prescription2','check-square','printer-fill','check','printer','check2-all','projector-fill','check2-circle','projector','check2-square','puzzle-fill','check2','puzzle','chevron-bar-contract','qr-code-scan','chevron-bar-down','qr-code','chevron-bar-expand','question-circle-fill','chevron-bar-left','question-circle','chevron-bar-right','question-diamond-fill','chevron-bar-up','question-diamond','chevron-compact-down','question-lg','chevron-compact-left','question-octagon-fill','chevron-compact-right','question-octagon','chevron-compact-up','question-square-fill','chevron-contract','question-square','chevron-double-down','question','chevron-double-left','quora','chevron-double-right','quote','chevron-double-up','r-circle-fill','chevron-down','r-circle','chevron-expand','r-square-fill','chevron-left','r-square','chevron-right','radar','chevron-up','radioactive','circle-fill','rainbow','circle-half','receipt-cutoff','circle-square','receipt','circle','reception-0','claude','reception-1','clipboard-check-fill','reception-2','clipboard-check','reception-3','clipboard-data-fill','reception-4','clipboard-data','record-btn-fill','clipboard-fill','record-btn','clipboard-heart-fill','record-circle-fill','clipboard-heart','record-circle','clipboard-minus-fill','record-fill','clipboard-minus','record','clipboard-plus-fill','record2-fill','clipboard-plus','record2','clipboard-pulse','recycle','clipboard-x-fill','reddit','clipboard-x','regex','clipboard','repeat-1','clipboard2-check-fill','repeat','clipboard2-check','reply-all-fill','clipboard2-data-fill','reply-all','clipboard2-data','reply-fill','clipboard2-fill','reply','clipboard2-heart-fill','rewind-btn-fill','clipboard2-heart','rewind-btn','clipboard2-minus-fill','rewind-circle-fill','clipboard2-minus','rewind-circle','clipboard2-plus-fill','rewind-fill','clipboard2-plus','rewind','clipboard2-pulse-fill','robot','clipboard2-pulse','rocket-fill','clipboard2-x-fill','rocket-takeoff-fill','clipboard2-x','rocket-takeoff','clipboard2','rocket','clock-fill','router-fill','clock-history','router','clock','rss-fill','cloud-arrow-down-fill','rss','cloud-arrow-down','rulers','cloud-arrow-up-fill','safe-fill','cloud-arrow-up','safe','cloud-check-fill','safe2-fill','cloud-check','safe2','cloud-download-fill','save-fill','cloud-download','save','cloud-drizzle-fill','save2-fill','cloud-drizzle','save2','cloud-fill','scissors','cloud-fog-fill','scooter','cloud-fog','screwdriver','cloud-fog2-fill','sd-card-fill','cloud-fog2','sd-card','cloud-hail-fill','search-heart-fill','cloud-hail','search-heart','cloud-haze-fill','search','cloud-haze','segmented-nav','cloud-haze2-fill','send-arrow-down-fill','cloud-haze2','send-arrow-down','cloud-lightning-fill','send-arrow-up-fill','cloud-lightning-rain-fill','send-arrow-up','cloud-lightning-rain','send-check-fill','cloud-lightning','send-check','cloud-minus-fill','send-dash-fill','cloud-minus','send-dash','cloud-moon-fill','send-exclamation-fill','cloud-moon','send-exclamation','cloud-plus-fill','send-fill','cloud-plus','send-plus-fill','cloud-rain-fill','send-plus','cloud-rain-heavy-fill','send-slash-fill','cloud-rain-heavy','send-slash','cloud-rain','send-x-fill','cloud-slash-fill','send-x','cloud-slash','send','cloud-sleet-fill','server','cloud-sleet','shadows','cloud-snow-fill','share-fill','cloud-snow','share','cloud-sun-fill','shield-check','cloud-sun','shield-exclamation','cloud-upload-fill','shield-fill-check','cloud-upload','shield-fill-exclamation','cloud','shield-fill-minus','clouds-fill','shield-fill-plus','clouds','shield-fill-x','cloudy-fill','shield-fill','cloudy','shield-lock-fill','code-slash','shield-lock','code-square','shield-minus','code','shield-plus','coin','shield-shaded','collection-fill','shield-slash-fill','collection-play-fill','shield-slash','collection-play','shield-x','collection','shield','columns-gap','shift-fill','columns','shift','command','shop-window','compass-fill','shop','compass','shuffle','cone-striped','sign-dead-end-fill','cone','sign-dead-end','controller','sign-do-not-enter-fill','cookie','sign-do-not-enter','copy','sign-intersection-fill','cpu-fill','sign-intersection-side-fill','cpu','sign-intersection-side','credit-card-2-back-fill','sign-intersection-t-fill','credit-card-2-back','sign-intersection-t','credit-card-2-front-fill','sign-intersection-y-fill','credit-card-2-front','sign-intersection-y','credit-card-fill','sign-intersection','credit-card','sign-merge-left-fill','crop','sign-merge-left','crosshair','sign-merge-right-fill','crosshair2','sign-merge-right','css','sign-no-left-turn-fill','cup-fill','sign-no-left-turn','cup-hot-fill','sign-no-parking-fill','cup-hot','sign-no-parking','cup-straw','sign-no-right-turn-fill','cup','sign-no-right-turn','currency-bitcoin','sign-railroad-fill','currency-dollar','sign-railroad','currency-euro','sign-stop-fill','currency-exchange','sign-stop-lights-fill','currency-pound','sign-stop-lights','currency-rupee','sign-stop','currency-yen','sign-turn-left-fill','cursor-fill','sign-turn-left','cursor-text','sign-turn-right-fill','cursor','sign-turn-right','dash-circle-dotted','sign-turn-slight-left-fill','dash-circle-fill','sign-turn-slight-left','dash-circle','sign-turn-slight-right-fill','dash-lg','sign-turn-slight-right','dash-square-dotted','sign-yield-fill','dash-square-fill','sign-yield','dash-square','signal','dash','signpost-2-fill','database-add','signpost-2','database-check','signpost-fill','database-dash','signpost-split-fill','database-down','signpost-split','database-exclamation','signpost','database-fill-add','sim-fill','database-fill-check','sim-slash-fill','database-fill-dash','sim-slash','database-fill-down','sim','database-fill-exclamation','sina-weibo','database-fill-gear','skip-backward-btn-fill','database-fill-lock','skip-backward-btn','database-fill-slash','skip-backward-circle-fill','database-fill-up','skip-backward-circle','database-fill-x','skip-backward-fill','database-fill','skip-backward','database-gear','skip-end-btn-fill','database-lock','skip-end-btn','database-slash','skip-end-circle-fill','database-up','skip-end-circle','database-x','skip-end-fill','database','skip-end','device-hdd-fill','skip-forward-btn-fill','device-hdd','skip-forward-btn','device-ssd-fill','skip-forward-circle-fill','device-ssd','skip-forward-circle','diagram-2-fill','skip-forward-fill','diagram-2','skip-forward','diagram-3-fill','skip-start-btn-fill','diagram-3','skip-start-btn','diamond-fill','skip-start-circle-fill','diamond-half','skip-start-circle','diamond','skip-start-fill','dice-1-fill','skip-start','dice-1','skype','dice-2-fill','slack','dice-2','slash-circle-fill','dice-3-fill','slash-circle','dice-3','slash-lg','dice-4-fill','slash-square-fill','dice-4','slash-square','dice-5-fill','slash','dice-5','sliders','dice-6-fill','sliders2-vertical','dice-6','sliders2','disc-fill','smartwatch','disc','snapchat','discord','snow','display-fill','snow2','display','snow3','displayport-fill','sort-alpha-down-alt','displayport','sort-alpha-down','distribute-horizontal','sort-alpha-up-alt','distribute-vertical','sort-alpha-up','door-closed-fill','sort-down-alt','door-closed','sort-down','door-open-fill','sort-numeric-down-alt','door-open','sort-numeric-down','dot','sort-numeric-up-alt','download','sort-numeric-up','dpad-fill','sort-up-alt','dpad','sort-up','dribbble','soundwave','dropbox','sourceforge','droplet-fill','speaker-fill','droplet-half','speaker','droplet','speedometer','duffle-fill','speedometer2','duffle','spellcheck','ear-fill','spotify','ear','square-fill','earbuds','square-half','easel-fill','square','easel','stack-overflow','easel2-fill','stack','easel2','star-fill','easel3-fill','star-half','easel3','star','egg-fill','stars','egg-fried','steam','egg','stickies-fill','eject-fill','stickies','eject','sticky-fill','emoji-angry-fill','sticky','emoji-angry','stop-btn-fill','emoji-astonished-fill','stop-btn','emoji-astonished','stop-circle-fill','emoji-dizzy-fill','stop-circle','emoji-dizzy','stop-fill','emoji-expressionless-fill','stop','emoji-expressionless','stoplights-fill','emoji-frown-fill','stoplights','emoji-frown','stopwatch-fill','emoji-grimace-fill','stopwatch','emoji-grimace','strava','emoji-grin-fill','stripe','emoji-grin','subscript','emoji-heart-eyes-fill','substack','emoji-heart-eyes','subtract','emoji-kiss-fill','suit-club-fill','emoji-kiss','suit-club','emoji-laughing-fill','suit-diamond-fill','emoji-laughing','suit-diamond','emoji-neutral-fill','suit-heart-fill','emoji-neutral','suit-heart','emoji-smile-fill','suit-spade-fill','emoji-smile-upside-down-fill','suit-spade','emoji-smile-upside-down','suitcase-fill','emoji-smile','suitcase-lg-fill','emoji-sunglasses-fill','suitcase-lg','emoji-sunglasses','suitcase','emoji-surprise-fill','suitcase2-fill','emoji-surprise','suitcase2','emoji-tear-fill','sun-fill','emoji-tear','sun','emoji-wink-fill','sunglasses','emoji-wink','sunrise-fill','envelope-arrow-down-fill','sunrise','envelope-arrow-down','sunset-fill','envelope-arrow-up-fill','sunset','envelope-arrow-up','superscript','envelope-at-fill','symmetry-horizontal','envelope-at','symmetry-vertical','envelope-check-fill','table','envelope-check','tablet-fill','envelope-dash-fill','tablet-landscape-fill','envelope-dash','tablet-landscape','envelope-exclamation-fill','tablet','envelope-exclamation','tag-fill','envelope-fill','tag','envelope-heart-fill','tags-fill','envelope-heart','tags','envelope-open-fill','taxi-front-fill','envelope-open-heart-fill','taxi-front','envelope-open-heart','telegram','envelope-open','telephone-fill','envelope-paper-fill','telephone-forward-fill','envelope-paper-heart-fill','telephone-forward','envelope-paper-heart','telephone-inbound-fill','envelope-paper','telephone-inbound','envelope-plus-fill','telephone-minus-fill','envelope-plus','telephone-minus','envelope-slash-fill','telephone-outbound-fill','envelope-slash','telephone-outbound','envelope-x-fill','telephone-plus-fill','envelope-x','telephone-plus','envelope','telephone-x-fill','eraser-fill','telephone-x','eraser','telephone','escape','tencent-qq','ethernet','terminal-dash','ev-front-fill','terminal-fill','ev-front','terminal-plus','ev-station-fill','terminal-split','ev-station','terminal-x','exclamation-circle-fill','terminal','exclamation-circle','text-center','exclamation-diamond-fill','text-indent-left','exclamation-diamond','text-indent-right','exclamation-lg','text-left','exclamation-octagon-fill','text-paragraph','exclamation-octagon','text-right','exclamation-square-fill','text-wrap','exclamation-square','textarea-resize','exclamation-triangle-fill','textarea-t','exclamation-triangle','textarea','exclamation','thermometer-half','exclude','thermometer-high','explicit-fill','thermometer-low','explicit','thermometer-snow','exposure','thermometer-sun','eye-fill','thermometer','eye-slash-fill','threads-fill','eye-slash','threads','eye','three-dots-vertical','eyedropper','three-dots','eyeglasses','thunderbolt-fill','facebook','thunderbolt','fan','ticket-detailed-fill','fast-forward-btn-fill','ticket-detailed','fast-forward-btn','ticket-fill','fast-forward-circle-fill','ticket-perforated-fill','fast-forward-circle','ticket-perforated','fast-forward-fill','ticket','fast-forward','tiktok','feather','toggle-off','feather2','toggle-on','file-arrow-down-fill','toggle2-off','file-arrow-down','toggle2-on','file-arrow-up-fill','toggles','file-arrow-up','toggles2','file-bar-graph-fill','tools','file-bar-graph','tornado','file-binary-fill','train-freight-front-fill','file-binary','train-freight-front','file-break-fill','train-front-fill','file-break','train-front','file-check-fill','train-lightrail-front-fill','file-check','train-lightrail-front','file-code-fill','translate','file-code','transparency','file-diff-fill','trash-fill','file-diff','trash','file-earmark-arrow-down-fill','trash2-fill','file-earmark-arrow-down','trash2','file-earmark-arrow-up-fill','trash3-fill','file-earmark-arrow-up','trash3','file-earmark-bar-graph-fill','tree-fill','file-earmark-bar-graph','tree','file-earmark-binary-fill','trello','file-earmark-binary','triangle-fill','file-earmark-break-fill','triangle-half','file-earmark-break','triangle','file-earmark-check-fill','trophy-fill','file-earmark-check','trophy','file-earmark-code-fill','tropical-storm','file-earmark-code','truck-flatbed','file-earmark-diff-fill','truck-front-fill','file-earmark-diff','truck-front','file-earmark-easel-fill','truck','file-earmark-easel','tsunami','file-earmark-excel-fill','tux','file-earmark-excel','tv-fill','file-earmark-fill','tv','file-earmark-font-fill','twitch','file-earmark-font','twitter-x','file-earmark-image-fill','twitter','file-earmark-image','type-bold','file-earmark-lock-fill','type-h1','file-earmark-lock','type-h2','file-earmark-lock2-fill','type-h3','file-earmark-lock2','type-h4','file-earmark-medical-fill','type-h5','file-earmark-medical','type-h6','file-earmark-minus-fill','type-italic','file-earmark-minus','type-strikethrough','file-earmark-music-fill','type-underline','file-earmark-music','type','file-earmark-pdf-fill','typescript','file-earmark-pdf','ubuntu','file-earmark-person-fill','ui-checks-grid','file-earmark-person','ui-checks','file-earmark-play-fill','ui-radios-grid','file-earmark-play','ui-radios','file-earmark-plus-fill','umbrella-fill','file-earmark-plus','umbrella','file-earmark-post-fill','unindent','file-earmark-post','union','file-earmark-ppt-fill','unity','file-earmark-ppt','universal-access-circle','file-earmark-richtext-fill','universal-access','file-earmark-richtext','unlock-fill','file-earmark-ruled-fill','unlock','file-earmark-ruled','unlock2-fill','file-earmark-slides-fill','unlock2','file-earmark-slides','upc-scan','file-earmark-spreadsheet-fill','upc','file-earmark-spreadsheet','upload','file-earmark-text-fill','usb-c-fill','file-earmark-text','usb-c','file-earmark-word-fill','usb-drive-fill','file-earmark-word','usb-drive','file-earmark-x-fill','usb-fill','file-earmark-x','usb-micro-fill','file-earmark-zip-fill','usb-micro','file-earmark-zip','usb-mini-fill','file-earmark','usb-mini','file-easel-fill','usb-plug-fill','file-easel','usb-plug','file-excel-fill','usb-symbol','file-excel','usb','file-fill','valentine','file-font-fill','valentine2','file-font','vector-pen','file-image-fill','view-list','file-image','view-stacked','file-lock-fill','vignette','file-lock','vimeo','file-lock2-fill','vinyl-fill','file-lock2','vinyl','file-medical-fill','virus','file-medical','virus2','file-minus-fill','voicemail','file-minus','volume-down-fill','file-music-fill','volume-down','file-music','volume-mute-fill','file-pdf-fill','volume-mute','file-pdf','volume-off-fill','file-person-fill','volume-off','file-person','volume-up-fill','file-play-fill','volume-up','file-play','vr','file-plus-fill','wallet-fill','file-plus','wallet','file-post-fill','wallet2','file-post','watch','file-ppt-fill','water','file-ppt','webcam-fill','file-richtext-fill','webcam','file-richtext','wechat','file-ruled-fill','whatsapp','file-ruled','wifi-1','file-slides-fill','wifi-2','file-slides','wifi-off','file-spreadsheet-fill','wifi','file-spreadsheet','wikipedia','file-text-fill','wind','file-text','window-dash','file-word-fill','window-desktop','file-word','window-dock','file-x-fill','window-fullscreen','file-x','window-plus','file-zip-fill','window-sidebar','file-zip','window-split','file','window-stack','files-alt','window-x','files','window','filetype-aac','windows','filetype-ai','wordpress','filetype-bmp','wrench-adjustable-circle-fill','filetype-cs','wrench-adjustable-circle','filetype-css','wrench-adjustable','filetype-csv','wrench','filetype-doc','x-circle-fill','filetype-docx','x-circle','filetype-exe','x-diamond-fill','filetype-gif','x-diamond','filetype-heic','x-lg','filetype-html','x-octagon-fill','filetype-java','x-octagon','filetype-jpg','x-square-fill','filetype-js','x-square','filetype-json','x','filetype-jsx','xbox','filetype-key','yelp','filetype-m4p','yin-yang','filetype-md','youtube','filetype-mdx','zoom-in','filetype-mov','zoom-out','filetype-mp3'
];

// 图标选择器状态
let iconPickerTargetInput = null;
let selectedIcon = null;

// 初始化图标选择器
function initIconPicker() {
    const modal = document.getElementById('iconPickerModal');
    const searchInput = document.getElementById('iconSearchInput');
    const confirmBtn = document.getElementById('confirmIconBtn');
    const iconGrid = document.getElementById('iconGrid');

    // 监听模态框显示事件
    modal.addEventListener('show.bs.modal', function (event) {
        // 获取触发按钮
        const button = event.relatedTarget;
        iconPickerTargetInput = button.getAttribute('data-target-input');

        // 重置状态
        selectedIcon = null;
        searchInput.value = '';
        confirmBtn.disabled = true;
        document.getElementById('selectedIconPreview').textContent = '未选择';

        // 加载图标
        renderIcons();

        // 如果目标输入框已有值，尝试选中对应的图标
        if (iconPickerTargetInput) {
            const targetInput = document.getElementById(iconPickerTargetInput);
            if (targetInput && targetInput.value) {
                const iconName = extractIconName(targetInput.value);
                if (iconName) {
                    // 延迟选中，等待图标渲染完成
                    setTimeout(() => {
                        selectIcon(iconName);
                    }, 100);
                }
            }
        }
    });

    // 监听搜索输入
    searchInput.addEventListener('input', function () {
        const keyword = this.value.toLowerCase().trim();
        renderIcons(keyword);
    });

    // 监听确定按钮
    confirmBtn.addEventListener('click', function () {
        if (selectedIcon && iconPickerTargetInput) {
            const targetInput = document.getElementById(iconPickerTargetInput);
            if (targetInput) {
                targetInput.value = 'bi bi-' + selectedIcon;

                // 触发 input 事件，以便其他脚本可以监听
                targetInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            // 关闭模态框
            const modalInstance = bootstrap.Modal.getInstance(modal);
            modalInstance.hide();
        }
    });
}

// 渲染图标列表
function renderIcons(keyword = '') {
    const iconGrid = document.getElementById('iconGrid');
    const noResults = document.getElementById('iconNoResults');
    const totalCount = document.getElementById('iconTotalCount');
    const filterCount = document.getElementById('iconFilterCount');

    // 过滤图标
    const filteredIcons = keyword
        ? bootstrapIcons.filter(icon => icon.includes(keyword))
        : bootstrapIcons;

    // 更新计数
    totalCount.textContent = bootstrapIcons.length;
    if (keyword) {
        filterCount.textContent = `，找到 ${filteredIcons.length} 个匹配的图标`;
    } else {
        filterCount.textContent = '';
    }

    // 显示/隐藏无结果提示
    if (filteredIcons.length === 0) {
        iconGrid.style.display = 'none';
        noResults.style.display = 'block';
        return;
    } else {
        iconGrid.style.display = 'grid';
        noResults.style.display = 'none';
    }

    // 渲染图标
    iconGrid.innerHTML = filteredIcons.map(icon => `
        <div class="icon-item" data-icon="${icon}" onclick="selectIcon('${icon}')">
            <i class="bi bi-${icon}"></i>
            <div class="icon-name">${icon}</div>
        </div>
    `).join('');
}

// 选择图标
function selectIcon(icon) {
    selectedIcon = icon;

    // 更新选中状态
    document.querySelectorAll('.icon-item').forEach(item => {
        if (item.getAttribute('data-icon') === icon) {
            item.classList.add('selected');
        } else {
            item.classList.remove('selected');
        }
    });

    // 更新预览和启用确定按钮
    document.getElementById('selectedIconPreview').innerHTML = `
        <i class="bi bi-${icon}"></i> bi bi-${icon}
    `;
    document.getElementById('confirmIconBtn').disabled = false;
}

/**
 * 检查图标 class 是否存在（仅验证完整格式）
 * 
 * @param {string} iconClass - 图标 class，必须是完整格式: "bi bi-house"
 * @returns {boolean} 如果图标存在且格式正确返回 true，否则返回 false
 * 
 * @example
 * isValidIconClass('bi bi-house')  // true
 * isValidIconClass('bi-house')      // false
 * isValidIconClass('house')         // false
 * isValidIconClass('bi bi-invalid') // false
 * isValidIconClass('bi bi-house extra') // false (不能有其他 class)
 */
function isValidIconClass(iconClass) {
    if (!iconClass || typeof iconClass !== 'string') {
        return false;
    }

    // 去除首尾空格
    const trimmed = iconClass.trim();
    
    // 必须是完整格式 "bi bi-{iconName}"，不能有其他内容
    if (!trimmed.startsWith('bi bi-')) {
        return false;
    }

    // 提取图标名称（移除 "bi bi-" 前缀）
    const iconName = trimmed.substring(6);
    
    // 检查是否包含空格（如果有空格说明格式不正确，如 "bi bi-house extra"）
    if (iconName.includes(' ')) {
        return false;
    }

    // 检查图标名称是否在列表中
    return bootstrapIcons.includes(iconName);
}

/**
 * 从图标 class 中提取图标名称
 * 
 * @param {string} iconClass - 图标 class
 * @returns {string|null} 图标名称，如果格式不正确返回 null
 * 
 * @example
 * extractIconName('bi bi-house')  // "house"
 * extractIconName('bi-house')      // "house"
 * extractIconName('house')         // "house"
 */
function extractIconName(iconClass) {
    if (!iconClass || typeof iconClass !== 'string') {
        return null;
    }

    let iconName = iconClass.trim();
    
    // 移除 "bi bi-" 前缀
    if (iconName.startsWith('bi bi-')) {
        iconName = iconName.substring(6);
    }
    // 移除 "bi-" 前缀
    else if (iconName.startsWith('bi-')) {
        iconName = iconName.substring(3);
    }
    // 移除 "bi " 前缀（带空格）
    else if (iconName.startsWith('bi ')) {
        iconName = iconName.substring(3);
    }

    // 验证提取的名称是否有效
    return bootstrapIcons.includes(iconName) ? iconName : null;
}

/**
 * 验证并格式化图标 class
 * 
 * @param {string} iconClass - 图标 class
 * @param {boolean} returnFullClass - 是否返回完整的 class 字符串（默认 true）
 * @returns {string|null} 格式化后的图标 class，如果无效返回 null
 * 
 * @example
 * formatIconClass('house')         // "bi bi-house"
 * formatIconClass('bi-house')      // "bi bi-house"
 * formatIconClass('bi bi-house')  // "bi bi-house"
 * formatIconClass('invalid')       // null
 * formatIconClass('house', false)  // "house"
 */
function formatIconClass(iconClass, returnFullClass = true) {
    const iconName = extractIconName(iconClass);
    if (!iconName) {
        return null;
    }
    
    return returnFullClass ? `bi bi-${iconName}` : iconName;
}

// 页面加载时初始化
document.addEventListener('DOMContentLoaded', function () {
    initIconPicker();
});

