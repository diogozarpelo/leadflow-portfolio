// LeadFlow Industrial - Local recent portfolio post

(() => {
  'use strict';

  function isPortuguese() {
    return document.documentElement.lang === 'pt';
  }

  function createTextElement(tagName, className, text) {
    const element = document.createElement(tagName);

    if (className) {
      element.className = className;
    }

    element.textContent = text;

    return element;
  }

  function createRecentPostCard(post) {
    const card = document.createElement('a');

    card.className = 'soro-blog-card';
    card.href = `blog/?post=${encodeURIComponent(post.slug)}`;
    card.dataset.slug = post.slug;

    const image = document.createElement('img');

    image.className = 'soro-blog-card-image';
    image.src = post.image;
    image.alt = post.imageAlt;
    image.loading = 'lazy';

    const content = document.createElement('div');

    content.className = 'soro-blog-card-content';

    const title = createTextElement(
      'h3',
      'soro-blog-card-title',
      post.title
    );

    const excerpt = createTextElement(
      'p',
      'soro-blog-card-excerpt',
      post.excerpt
    );

    const date = createTextElement(
      'time',
      'soro-blog-card-date',
      post.displayDate
    );

    date.dateTime = post.publishedAt;

    content.append(title, excerpt, date);
    card.append(image, content);

    return card;
  }

  function renderRecentPost() {
    const section = document.getElementById('recent-posts');
    const grid = document.getElementById('recentPostsGrid');
    const status = document.getElementById('recentPostsStatus');
    const posts = window.LEADFLOW_DEMO_POSTS || [];

    if (!section || !grid || !status) {
      return;
    }

    if (!isPortuguese() || posts.length === 0) {
      section.hidden = true;
      return;
    }

    grid.replaceChildren(createRecentPostCard(posts[0]));
    grid.setAttribute('aria-busy', 'false');

    status.hidden = true;
    section.hidden = false;
  }

  window.addEventListener(
    'leadflow:languagechange',
    renderRecentPost
  );

  if (document.readyState === 'loading') {
    document.addEventListener(
      'DOMContentLoaded',
      renderRecentPost,
      { once: true }
    );
  } else {
    renderRecentPost();
  }
})();