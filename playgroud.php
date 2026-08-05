
/* ===== FULL WIDTH (desktop only) ===== */

@media (min-width: 1000px) {
.skin-vector-2022 .mw-page-container,
.skin-vector-2022 .mw-content-container,
.skin-vector-2022 #mw-content-container {
max-width: none !important;
}
}
/* ===== MINERVA (MOBILE) OVERRIDES ===== */

/* Homepage section order: sections right after hero image */
body.skin-minerva .mainpage-wrap {
display: flex;
flex-direction: column;
}
body.skin-minerva .mainpage-wrap .hero-img       { order: 1; }
body.skin-minerva .mainpage-wrap .home           { order: 2; }
body.skin-minerva .mainpage-wrap .banner-cont    { order: 3; }
body.skin-minerva .mainpage-wrap .about-section  { order: 4; }

/* Card grid: single column on mobile */
body.skin-minerva .row {
display: flex !important;
flex-direction: column !important;
gap: 16px !important;
}
body.skin-minerva .column {
width: 100% !important;
max-width: 100% !important;
}
body.skin-minerva .fakeimg img {
width: 100% !important;
height: 180px !important;
object-fit: cover !important;
}
body.skin-minerva .hero-img img {
width: 100% !important;
height: auto !important;
object-fit: contain !important;
}
body.skin-minerva .btn {
display: block !important;
text-align: center !important;
}

/* ===== HIDE APPEARANCE PANEL ===== */
.skin-vector-2022 .mw-portlet-appearance,
.skin-vector-2022 #vector-page-tools-pinned-container .mw-portlet-appearance,
.skin-vector-2022 .vector-appearance {
display: none !important;
}

/* ===== HIDE EDIT SECTION LINKS ===== */
.mw-editsection {
display: none !important;
}

/* ===== CARD LAYOUT ===== */
.card {
background: var(--background-color-base, #fff);
border-radius: 10px;
box-shadow: 0 2px 12px rgba(0,0,0,0.1);
overflow: hidden;
transition: transform 0.2s ease, box-shadow 0.2s ease;
height: 100%;
display: flex;
flex-direction: column;
}

.card:hover {
transform: translateY(-4px);
box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

/* ===== ROW / COLUMN GRID ===== */
.row {
display: flex !important;
flex-wrap: wrap !important;
gap: 20px !important;
padding: 16px 20px !important;
box-sizing: border-box !important;
}

.column {
flex: 1 1 260px !important;
max-width: min(100%, calc(33.333% - 14px)) !important;
min-width: 0 !important;
box-sizing: border-box !important;
}

/* ===== IMAGES ===== */
.fakeimg {
overflow: hidden;
height: clamp(140px, 25vw, 220px);
background: #f0f0f0;
}

.fakeimg img,
.fakeimg a img {
width: 100% !important;
height: 100% !important;
object-fit: cover !important;
display: block !important;
}

/* ===== CARD CONTENT ===== */
.cha-cont {
padding: 16px !important;
display: flex !important;
flex-direction: column !important;
flex-grow: 1 !important;
}
.cha-cont .cha-title-row {
display: flex !important;
align-items: baseline !important;
gap: 8px !important;
margin: 0 0 12px 0 !important;
}

.cha-title {
font-size: 11px !important;
color: #888 !important;
text-transform: uppercase !important;
letter-spacing: 0.8px !important;
display: block !important;
margin: 0 0 2px 0 !important;   /* was 6px — reduce gap here */
border: none !important;
background: none !important;
padding: 0 !important;
}

.cha-cont h2 {
font-size: 16px !important;
margin: 0 !important;                /* was: 0 0 12px 0 */
color: #1a1a2e !important;
line-height: 1.4 !important;
border: none !important;
flex-grow: 1 !important;
}

/* ===== BUTTONS ===== */
.btn-grp {
margin-top: auto !important;
padding-top: 8px !important;
}

.btn-2 {
background-color: #98A44C !important;
color: #fff !important;
display: block !important;
text-align: center !important;
width: 100% !important;
box-sizing: border-box !important;
padding: 10px 18px !important;
border-radius: 5px !important;
font-size: 13px !important;
font-weight: 600 !important;
text-decoration: none !important;
}

.btn-2 a,
.btn-2 a:visited {
color: #fff !important;
text-decoration: none !important;
}

.btn-2:hover {
background-color: #fff !important;
border: 2px solid #98A44C !important;
}

.btn-2:hover a,
.btn-2:hover a:visited {
color: #98A44C !important;
}

/* ===== HERO IMAGE ===== */
.hero-img {
width: 100% !important;
overflow: visible !important;
}

.hero-img img,
.hero-img a img {
width: 100% !important;
height: auto !important;
object-fit: contain !important;
display: block !important;
}

/* ===== HERO IMAGE — DESKTOP/MOBILE SWAP =====
Pairs with wikitext like:
<div class="hero-img hero-desktop">[[File:Hero-img-3.webp|1200px|alt=...]]</div>
<div class="hero-img hero-mobile">[[File:Hero-mobile.png|900px|alt=...]]</div>
Toggled by skin class (mobile skin = Minerva) rather than viewport width,
since MobileFrontend already decides which skin to serve. */
.hero-mobile {
display: none !important;
}

body.skin-minerva .hero-mobile {
display: block !important;
}

body.skin-minerva .hero-desktop {
display: none !important;
}

/* ===== BANNER ===== */
.banner-cont {
background: var(--background-color-base, #fff) !important;
padding: 24px 20px !important;
box-sizing: border-box !important;
}

.banner-cont p {
font-size: 15px !important;
line-height: 1.7 !important;
color: #333 !important;
margin: 0 !important;
}

/* ===== TITLES ===== */
.title h1 {
font-size: clamp(22px, 5vw, 32px) !important;
margin: 16px 20px !important;
color: #1a1a2e !important;
border-bottom: 2px solid #98A44C !important;
padding-bottom: 12px !important;
}

.ch-title {
font-size: clamp(18px, 4vw, 22px) !important;
color: #595825 !important;
margin: 20px 20px 8px !important;
padding-bottom: 8px !important;
border-bottom: 2px solid #98A44C !important;
}

/* ===== GOOGLE TRANSLATE ===== */
#google-translate-sidebar {
padding: 8px 5px !important;
}

#google_translate_element {
padding: 4px 0 !important;
}

#google_translate_element .goog-te-gadget {
font-size: 0 !important;
}

#google_translate_element select,
#google_translate_element .goog-te-gadget select {
width: 100% !important;
padding: 5px !important;
border-radius: 4px !important;
border: 1px solid #ddd !important;
font-size: 12px !important;
background: #fff !important;
cursor: pointer !important;
}

#google_translate_element .goog-te-gadget span {
display: none !important;
}

.goog-te-banner-frame {
display: none !important;
}

body {
top: 0 !important;
}

/* ===== MOBILE ===== */
@media (max-width: 479px) {
.column {
max-width: 100% !important;
flex-basis: 100% !important;
}

.row {
gap: 14px !important;
padding: 12px !important;
}
}

/* ===== TABLET (2-column grid) ===== */
@media (min-width: 480px) and (max-width: 999px) {
.column {
max-width: calc(50% - 10px) !important;
flex-basis: calc(50% - 10px) !important;
}

.row {
gap: 14px !important;
padding: 14px !important;
}
}

/* ===== STICKY SIDEBAR (desktop only) ===== */
@media screen and (min-width: 1000px) {
.mw-sidebar,
#vector-main-menu-pinned-container,
.vector-main-menu-pinned-container {
position: sticky !important;
top: 0 !important;
max-height: 100vh !important;
overflow-y: auto !important;
overflow-x: hidden !important;
}

/* Hide scrollbar but keep functionality */
#vector-main-menu-pinned-container::-webkit-scrollbar {
width: 4px;
}
#vector-main-menu-pinned-container::-webkit-scrollbar-thumb {
background: #ccc;
border-radius: 4px;
}
}

/* ===== HIDE TRANSLATE FROM TOOLS MENU ===== */
#p-tb #t-uls,
#p-tb li:has(a[href*="Special:Translate"]) {
display: none !important;
}

/* ===== TRANSLATE IN SIDEBAR STYLING ===== */
#p-sidebar-translate {
margin-top: 4px !important;
}

#p-sidebar-translate .vector-menu-heading {
display: none !important;
}

#sidebar-translate-link {
display: flex !important;
align-items: center !important;
gap: 6px !important;
padding: 6px 10px !important;
color: #98A44C !important;
font-weight: 600 !important;
font-size: 13px !important;
text-decoration: none !important;
border-radius: 4px !important;
transition: background 0.2s !important;
}

#sidebar-translate-link:hover {
background: #f4f0d9 !important;
color: #595825 !important;
}
/* ===== HIDE SITE TITLE TEXT NEXT TO LOGO ===== */
.skin-vector-2022 .mw-logo-container > *:not(.mw-logo-icon) {
display: none !important;
}
/* ===== MOBILE OVERRIDES (Vector 2022 responsive mode) ===== */
@media screen and (max-width: 719px) {
.row {
display: flex !important;
flex-direction: column !important;
gap: 16px !important;
}
.column {
width: 100% !important;
max-width: 100% !important;
}
.fakeimg img {
width: 100% !important;
height: 180px !important;
object-fit: cover !important;
}
.hero-img img {
width: 100% !important;
height: auto !important;
object-fit: contain !important;
}
.btn {
display: block !important;
text-align: center !important;
}
}
.title-bg {
background: #CCCE84;
padding: 15px;
font-weight: 600;
color: #000;
font-size: 28px;
margin: unset;
text-align: center;
}

.cont-bg {
background: #FBFCE3;
padding: 15px;
font-weight: 400;
color: #212529;
font-size: 16px;
margin: unset;
line-height: 1.5;
}
