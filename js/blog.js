// LeadFlow Industrial - Local portfolio blog

(() => {
  'use strict';

  const widgetElement = document.getElementById('soro-blog');
  const statusElement = document.getElementById('soro-status');
  const loadingIndicator = document.querySelector(
    '.blog-loading-indicator'
  );
  const paginationElement =
    document.getElementById('blogPagination');
  const listEndActionElement =
    document.getElementById('blogListEndAction');

  function createTextElement(tagName, className, text) {
    const element = document.createElement(tagName);

    if (className) {
      element.className = className;
    }

    element.textContent = text;

    return element;
  }

  function resolveImagePath(imagePath) {
    return `../${imagePath}`;
  }

  function createArticleCard(post) {
    const card = document.createElement('a');

    card.className = 'soro-blog-card';
    card.href = `?post=${encodeURIComponent(post.slug)}`;
    card.dataset.slug = post.slug;

    const image = document.createElement('img');

    image.className = 'soro-blog-card-image';
    image.src = resolveImagePath(post.image);
    image.alt = post.imageAlt;
    image.loading = 'lazy';

    const content = document.createElement('div');

    content.className = 'soro-blog-card-content';

    const title = createTextElement(
      'h2',
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

  function renderArticleList(posts) {
    const list = document.createElement('div');

    list.className = 'soro-blog-list';

    posts.forEach((post) => {
      list.appendChild(createArticleCard(post));
    });

    widgetElement.replaceChildren(list);

    if (paginationElement) {
      paginationElement.hidden = true;
    }

    if (listEndActionElement) {
      listEndActionElement.hidden = false;
    }
  }

  function createArticleActions() {
    const actions = document.createElement('div');

    actions.className = 'blog-article-end-action';

    const allArticlesLink = createTextElement(
      'a',
      'btn-outline',
      'Ver todos os artigos'
    );

    allArticlesLink.href = './';

    const homeLink = createTextElement(
      'a',
      'btn-outline',
      'Voltar para o site'
    );

    homeLink.href = '../index.html';

    actions.append(allArticlesLink, homeLink);

    return actions;
  }

  function renderArticle(post) {
    const article = document.createElement('article');

    article.className = 'soro-blog-article visible';

    const header = document.createElement('header');

    header.className = 'soro-blog-header';

    const title = createTextElement(
      'h1',
      'demo-blog-article-title',
      post.title
    );

    const date = createTextElement(
      'time',
      'soro-blog-card-date',
      post.displayDate
    );

    date.dateTime = post.publishedAt;

    const image = document.createElement('img');

    image.className = 'demo-blog-article-image';
    image.src = resolveImagePath(post.image);
    image.alt = post.imageAlt;

    header.append(title, date, image);

    const body = document.createElement('div');

    body.className = 'demo-blog-article-body';

    post.sections.forEach((sectionData) => {
      const section = document.createElement('section');

      section.appendChild(
        createTextElement(
          'h2',
          'demo-blog-section-title',
          sectionData.heading
        )
      );

      sectionData.paragraphs.forEach((paragraphText) => {
        section.appendChild(
          createTextElement('p', '', paragraphText)
        );
      });

      body.appendChild(section);
    });

    article.append(header, body, createArticleActions());
    widgetElement.replaceChildren(article);

    if (paginationElement) {
      paginationElement.hidden = true;
    }

    if (listEndActionElement) {
      listEndActionElement.hidden = true;
    }
  }

  function finishLoading() {
    widgetElement.setAttribute('aria-busy', 'false');

    if (statusElement) {
      statusElement.hidden = true;
    }

    if (loadingIndicator) {
      loadingIndicator.hidden = true;
    }
  }

  function renderBlog() {
    if (!widgetElement) {
      return;
    }

    const posts = window.LEADFLOW_DEMO_POSTS || [];
    const requestedSlug =
      new URLSearchParams(window.location.search).get('post');

    const requestedPost = posts.find(
      (post) => post.slug === requestedSlug
    );

    if (requestedPost) {
      renderArticle(requestedPost);
    } else {
      renderArticleList(posts);
    }

    finishLoading();
  }

  function initializeSampleModalTriggers() {
    document
      .querySelectorAll('[data-open-sample-modal]')
      .forEach((trigger) => {
        trigger.addEventListener('click', () => {
          const productName =
            trigger.dataset.product || 'SLES 70%';

          if (typeof openSampleModal === 'function') {
            openSampleModal(productName);
          }
        });
      });
  }

  function initializeBlog() {
    renderBlog();
    initializeSampleModalTriggers();
  }

  if (document.readyState === 'loading') {
    document.addEventListener(
      'DOMContentLoaded',
      initializeBlog,
      { once: true }
    );
  } else {
    initializeBlog();
  }
})();