# StudioAstra Help Center — Implementation Plan

## Tech Stack
- **Backend:** Symfony 7 (PHP 8.2+)
- **Database:** MySQL (Doctrine ORM)
- **Frontend:** Twig + Tailwind CSS + Stimulus (Symfony UX)
- **Block Editor:** Editor.js (Notion-like block editor)
- **Search:** MySQL FULLTEXT search (simple, no extra dependency)
- **Font:** Satoshi

## Database Schema

### `admin` — Back-office users
| Column | Type |
|--------|------|
| id | INT PK |
| email | VARCHAR(180) UNIQUE |
| password | VARCHAR(255) (hashed) |
| roles | JSON |
| created_at | DATETIME |

### `section` — Top-level categories (e.g., "Factures")
| Column | Type |
|--------|------|
| id | INT PK |
| title | VARCHAR(255) |
| slug | VARCHAR(255) UNIQUE |
| description | TEXT NULL |
| icon | VARCHAR(50) NULL |
| position | INT (sort order) |
| created_at | DATETIME |
| updated_at | DATETIME |

### `subsection` — Under a section (e.g., "Demander un paiement")
| Column | Type |
|--------|------|
| id | INT PK |
| section_id | INT FK → section |
| title | VARCHAR(255) |
| slug | VARCHAR(255) |
| description | TEXT NULL |
| icon | VARCHAR(50) NULL |
| position | INT |
| created_at | DATETIME |
| updated_at | DATETIME |
| UNIQUE(section_id, slug) | |

### `article` — The actual help content
| Column | Type |
|--------|------|
| id | INT PK |
| subsection_id | INT FK → subsection |
| title | VARCHAR(255) |
| slug | VARCHAR(255) |
| content | JSON (Editor.js blocks) |
| search_text | TEXT (plain text extracted for FULLTEXT) |
| is_published | BOOLEAN |
| position | INT |
| created_at | DATETIME |
| updated_at | DATETIME |
| published_at | DATETIME NULL |
| UNIQUE(subsection_id, slug) | |
| FULLTEXT(title, search_text) | |

### `article_version` — Version history
| Column | Type |
|--------|------|
| id | INT PK |
| article_id | INT FK → article |
| content | JSON |
| title | VARCHAR(255) |
| version_number | INT |
| created_by | VARCHAR(180) |
| created_at | DATETIME |

### `article_feedback` — "Was this helpful?"
| Column | Type |
|--------|------|
| id | INT PK |
| article_id | INT FK → article |
| is_helpful | BOOLEAN |
| comment | TEXT NULL |
| ip_address | VARCHAR(45) |
| created_at | DATETIME |

## URL Structure

### Public (frontend)
- `/` — Home: search bar + list of all sections
- `/{section-slug}` — Section page: list of subsections & articles
- `/{section-slug}/{subsection-slug}` — Subsection: list of articles
- `/{section-slug}/{subsection-slug}/{article-slug}` — Article page
- `/recherche?q=...` — Search results

### Back-office (admin)
- `/admin/login` — Login
- `/admin` — Dashboard
- `/admin/sections` — CRUD sections
- `/admin/sections/{id}/subsections` — CRUD subsections
- `/admin/articles` — CRUD articles (with Editor.js)
- `/admin/articles/{id}/versions` — View version history

### Public API
- `/api/v1/sections` — List sections
- `/api/v1/sections/{slug}` — Section detail with subsections
- `/api/v1/articles/{id}` — Article content (JSON)
- `/api/v1/search?q=...` — Search articles

## Project Structure
```
├── assets/
│   ├── styles/
│   │   └── app.css          (Tailwind + custom vars)
│   ├── controllers/          (Stimulus controllers)
│   │   ├── editor_controller.js
│   │   ├── search_controller.js
│   │   └── feedback_controller.js
│   └── app.js
├── config/
├── migrations/
├── public/
│   └── uploads/              (article images)
├── src/
│   ├── Command/
│   │   └── CreateAdminCommand.php
│   ├── Controller/
│   │   ├── Front/
│   │   │   ├── HomeController.php
│   │   │   ├── SectionController.php
│   │   │   ├── ArticleController.php
│   │   │   └── SearchController.php
│   │   ├── Admin/
│   │   │   ├── DashboardController.php
│   │   │   ├── SectionCrudController.php
│   │   │   ├── SubsectionCrudController.php
│   │   │   ├── ArticleCrudController.php
│   │   │   └── ImageUploadController.php
│   │   └── Api/
│   │       └── ApiController.php
│   ├── Entity/
│   ├── Repository/
│   ├── Form/
│   ├── Service/
│   │   ├── SearchService.php
│   │   └── ArticleVersionService.php
│   └── Security/
│       └── AdminAuthenticator.php
├── templates/
│   ├── base.html.twig
│   ├── front/
│   │   ├── home.html.twig
│   │   ├── section.html.twig
│   │   ├── subsection.html.twig
│   │   ├── article.html.twig
│   │   └── search.html.twig
│   ├── admin/
│   │   ├── base.html.twig
│   │   ├── login.html.twig
│   │   ├── dashboard.html.twig
│   │   ├── section/
│   │   ├── subsection/
│   │   └── article/
│   └── components/
│       ├── breadcrumbs.html.twig
│       ├── toc.html.twig
│       ├── feedback.html.twig
│       ├── search_bar.html.twig
│       └── navbar.html.twig
└── docker-compose.yml (optional, for local MySQL)
```

## Implementation Steps

### Phase 1 — Symfony project setup
1. Init Symfony project with webapp skeleton
2. Configure Tailwind CSS (via Symfony AssetMapper or Webpack Encore)
3. Configure MySQL connection
4. Set up Satoshi font + color variables
5. Create base Twig layout

### Phase 2 — Database entities & migrations
6. Create all entities (Admin, Section, Subsection, Article, ArticleVersion, ArticleFeedback)
7. Generate and run migrations
8. Create `app:create-admin` CLI command

### Phase 3 — Admin back-office
9. Admin login (Symfony Security)
10. Section CRUD
11. Subsection CRUD
12. Article CRUD with Editor.js integration
13. Image upload endpoint
14. Version history view

### Phase 4 — Public frontend
15. Home page (sections grid + search)
16. Section page
17. Subsection page
18. Article page (render Editor.js blocks, TOC, breadcrumbs, feedback)
19. Search page
20. "Was this helpful?" feedback (AJAX)

### Phase 5 — Public API
21. REST endpoints for sections, articles, search

### Phase 6 — Polish
22. Responsive design
23. SEO meta tags
24. 404 pages
