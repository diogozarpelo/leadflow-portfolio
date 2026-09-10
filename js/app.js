// LeadFlow Industrial - Application Logic & Dynamic Features


let currentLang = 'pt';

document.addEventListener('DOMContentLoaded', () => {
  initLanguage();
  initHeaderScroll();
  initMobileMenu();
  initLeadModals();
  initLeadForms();
  initLeadInputMasks();
});

// Expose language setter globally for inline onclick handlers
window._setLang = function (lang) {
  setLanguage(lang);
  const dd = document.getElementById('langDropdown');
  if (dd) dd.classList.remove('open');
};

// Internationalization (i18n) Engine
function initLanguage() {
  const isBlogPage = document.body.classList.contains('blog-page');
  const savedLang = isBlogPage
    ? 'pt'
    : localStorage.getItem('leadflow_chem_lang') || 'pt';

  setLanguage(savedLang, { persist: !isBlogPage });

  const langBtn = document.getElementById('langBtn');
  const langDropdown = document.getElementById('langDropdown');

  if (!langBtn || !langDropdown) return;

  // Open/close dropdown on button click
  langBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    langDropdown.classList.toggle('open');
  });

  // Close when clicking anywhere outside
  document.addEventListener('click', function () {
    langDropdown.classList.remove('open');
  });

  // Prevent clicks inside dropdown from closing it (except on options)
  langDropdown.addEventListener('click', function (e) {
    e.stopPropagation();
  });
}

function setLanguage(lang, { persist = true } = {}) {
  if (!translations || !translations[lang]) return;
  currentLang = lang;

  if (persist) {
    try {
      localStorage.setItem('leadflow_chem_lang', lang);
    } catch (e) { }
  }

  // Set HTML attributes (lang & RTL support for Arabic)
  document.documentElement.setAttribute('lang', lang);
  if (lang === 'ar') {
    document.documentElement.setAttribute('dir', 'rtl');
  } else {
    document.documentElement.setAttribute('dir', 'ltr');
  }

  // Update Baby Safety image according to the selected language
  const babySafetyImage = document.getElementById('babySafetyImage');

  if (babySafetyImage) {
    babySafetyImage.src = lang === 'pt'
      ? 'assets/applications/sles70-baby-safety-pt.jpg'
      : 'assets/applications/sles70-baby-safety-en.jpg';
  }

  // Update text content of all data-i18n elements
  const elements = document.querySelectorAll('[data-i18n]');
  elements.forEach(el => {
    const key = el.getAttribute('data-i18n');
    if (translations[lang] && translations[lang][key] !== undefined) {
      const val = translations[lang][key];
      if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
        el.placeholder = val;
      } else if (el.tagName === 'META') {
        el.setAttribute('content', val);
      } else {
        el.textContent = val;
      }
    }
  });

  document.querySelectorAll('[data-i18n-aria-label]').forEach(element => {
    const label = translations[lang][element.dataset.i18nAriaLabel];
    if (label !== undefined) element.setAttribute('aria-label', label);
  });

  // Update selected class in dropdown
  document.querySelectorAll('.lang-option').forEach(opt => {
    if (opt.getAttribute('data-lang') === lang) {
      opt.classList.add('selected');
    } else {
      opt.classList.remove('selected');
    }
  });

  // Update current lang label in nav
  const currentLangLabel = document.getElementById('currentLangLabel');
  if (currentLangLabel) {
    const names = { pt: 'PT', en: 'EN', zh: '中文', ar: 'العربية', es: 'ES' };
    currentLangLabel.textContent = names[lang] || lang.toUpperCase();
  }

  updatePortugueseOnlyContent(lang);
}

function updatePortugueseOnlyContent(lang) {
  const isPortuguese = lang === 'pt';

  document.querySelectorAll('[data-pt-only]').forEach(element => {
    element.hidden = !isPortuguese;
  });

  window.dispatchEvent(
    new CustomEvent('leadflow:languagechange', {
      detail: { lang }
    })
  );
}

// Header Scroll Effect
function initHeaderScroll() {
  const header = document.getElementById('mainHeader');
  if (!header) return;

  window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  });
}

// Mobile Menu Toggle
function initMobileMenu() {
  const toggleBtn = document.getElementById('mobileToggle');
  const navMenu = document.getElementById('navMenu');

  if (toggleBtn && navMenu) {
    toggleBtn.addEventListener('click', () => {
      navMenu.classList.toggle('active');
    });

    document.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        navMenu.classList.remove('active');
      });
    });
  }
}

// Shared keyboard, focus and background control for lead dialogs.
let activeLeadModal = null;

function initLeadModals() {
  document.querySelectorAll('[data-lead-modal]').forEach(modal => {
    modal.querySelector('[data-close-modal]')?.addEventListener(
      'click', () => closeLeadModal(modal)
    );

    modal.addEventListener('click', event => {
      if (event.target === modal) closeLeadModal(modal);
    });

  });

  document.addEventListener('keydown', event => {
    if (!activeLeadModal) return;
    const { modal } = activeLeadModal;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeLeadModal(modal);
    } else if (event.key === 'Tab') {
      trapLeadModalFocus(event, modal);
    }
  });

  document.querySelectorAll('[data-open-modal]').forEach(trigger => {
    trigger.addEventListener('click', () => {
      if (trigger.dataset.openModal === 'sampleModal') {
        openSampleModal(trigger);
      } else {
        openLeadModal(trigger.dataset.openModal, trigger);
      }
    });
  });
}

function leadModalFocusableElements(modal) {
  return Array.from(modal.querySelectorAll(
    'a[href], button, input, select, textarea, [tabindex]'
  )).filter(element =>
    element.tabIndex >= 0 &&
    !element.matches(':disabled') &&
    !element.closest('[inert], [hidden], [aria-hidden="true"]') &&
    element.getClientRects().length > 0 &&
    getComputedStyle(element).visibility === 'visible'
  );
}

function trapLeadModalFocus(event, modal) {
  const elements = leadModalFocusableElements(modal);
  const first = elements[0];
  const last = elements[elements.length - 1];
  const currentIndex = elements.indexOf(document.activeElement);

  if (!first) {
    event.preventDefault();
    modal.querySelector('.modal-card')?.focus();
  } else if (event.shiftKey && currentIndex <= 0) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey &&
    (currentIndex === -1 || document.activeElement === last)) {
    event.preventDefault();
    first.focus();
  }
}

function openLeadModal(modalId, trigger = document.activeElement) {
  const modal = document.getElementById(modalId);
  if (!modal || activeLeadModal?.modal === modal) return;

  if (activeLeadModal) {
    // Switching dialogs should return focus to the original page trigger.
    trigger = activeLeadModal.trigger;
    closeLeadModal(activeLeadModal.modal, { restoreFocus: false });
  }

  const background = new Map();
  const bodyWasLocked = document.body.classList.contains('modal-open');
  modal.inert = false;
  modal.setAttribute('aria-hidden', 'false');
  modal.classList.add('open');
  document.body.classList.add('modal-open');

  // Walk ancestors as well, so dialogs can live inside a page container.
  for (let branch = modal; branch.parentElement; branch = branch.parentElement) {
    for (const sibling of branch.parentElement.children) {
      if (sibling !== branch && sibling instanceof HTMLElement) {
        background.set(sibling, sibling.inert);
        sibling.inert = true;
      }
    }
    if (branch.parentElement === document.body) break;
  }

  activeLeadModal = { modal, trigger, background, bodyWasLocked };
  const focusTarget = leadModalFocusableElements(modal)[0] ||
    modal.querySelector('.modal-card');
  focusTarget?.focus();
}

function closeLeadModal(modal, { restoreFocus = true } = {}) {
  if (!modal || activeLeadModal?.modal !== modal) return;

  const { trigger, background, bodyWasLocked } = activeLeadModal;
  background.forEach((wasInert, element) => { element.inert = wasInert; });
  document.body.classList.toggle('modal-open', bodyWasLocked);

  if (restoreFocus && trigger instanceof HTMLElement &&
    trigger.isConnected && trigger.getClientRects().length > 0 &&
    !trigger.closest('[inert]') && !trigger.matches(':disabled')) {
    trigger.focus();
  }

  // Move focus out before hiding the dialog from assistive technology.
  if (modal.contains(document.activeElement)) document.activeElement.blur();
  modal.classList.remove('open');
  modal.setAttribute('aria-hidden', 'true');
  modal.inert = true;
  activeLeadModal = null;
}

// Public entry point also used by the blog's dynamically generated buttons.
function openSampleModal(trigger = document.activeElement) {
  const success = document.getElementById('modalSuccess');
  if (success) success.style.display = 'none';
  openLeadModal('sampleModal', trigger);
}

function closeSampleModal(options = {}) {
  closeLeadModal(document.getElementById('sampleModal'), options);
}

// Backend local utilizado somente durante o desenvolvimento
const LOCAL_LEAD_API_URL =
  'http://127.0.0.1:8000/api/leads';

const USE_LOCAL_LEAD_API = [
  '127.0.0.1',
  'localhost'
].includes(window.location.hostname);

const LEAD_REQUEST_HEADERS = Object.freeze({
  'Content-Type': 'application/json',
  Accept: 'application/json'
});

function createPortfolioDemoResponse() {
  return {
    ok: true,
    json: async () => ({
      success: true,
      demo: true
    })
  };
}

async function submitLeadRequest(
  apiPayload,
  fallbackErrorMessage
) {
  const response = USE_LOCAL_LEAD_API
    ? await fetch(LOCAL_LEAD_API_URL, {
      method: 'POST',
      headers: LEAD_REQUEST_HEADERS,
      body: JSON.stringify(apiPayload)
    })
    : createPortfolioDemoResponse();

  const data = await response.json();

  if (
    !response.ok ||
    data.success === false ||
    data.success === 'false'
  ) {
    throw new Error(
      data.message || fallbackErrorMessage
    );
  }

  return data;
}

// Lead Forms
function initLeadForms() {
  document
    .getElementById('contactForm')
    ?.addEventListener('submit', submitContactForm);

  document
    .getElementById('modalForm')
    ?.addEventListener('submit', submitSampleRequestForm);

  document
    .getElementById('whatsappLeadForm')
    ?.addEventListener('submit', submitWhatsappLead);
}

function readTrimmedInput(inputId) {
  return document
    .getElementById(inputId)
    ?.value
    .trim() || '';
}

function selectedOptionText(select) {
  return select
    ?.selectedOptions[0]
    ?.textContent
    .trim() || '';
}

function setSubmitButtonState(
  button,
  isSubmitting,
  originalText
) {
  if (!button) {
    return;
  }

  button.disabled = isSubmitting;
  button.textContent = isSubmitting
    ? translations[currentLang]?.form_sending || 'Enviando...'
    : originalText;
}

async function handleLeadFormSubmission({
  form,
  apiPayload,
  successElement,
  successText,
  fallbackErrorMessage,
  consoleErrorMessage,
  alertMessage
}) {
  const submitButton =
    form.querySelector('button[type="submit"]');

  const originalButtonText =
    submitButton?.textContent.trim() || '';

  if (successElement) {
    successElement.style.display = 'none';
  }

  setSubmitButtonState(
    submitButton,
    true,
    originalButtonText
  );

  try {
    await submitLeadRequest(
      apiPayload,
      fallbackErrorMessage
    );

    form.reset();

    if (successElement) {
      if (successText) {
        successElement.textContent = successText;
      }

      successElement.style.display = 'block';
    }
  } catch (error) {
    console.error(consoleErrorMessage, error);
    alert(alertMessage);
  } finally {
    setSubmitButtonState(
      submitButton,
      false,
      originalButtonText
    );
  }
}

async function submitContactForm(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const name = readTrimmedInput('contact_name');
  const email = readTrimmedInput('contact_email');
  const company = readTrimmedInput('contact_company');
  const cnpj = readTrimmedInput('contact_cnpj');
  const phone = readTrimmedInput('contact_phone');
  const message = readTrimmedInput('contact_message');

  const sectorSelect =
    document.getElementById('contact_sector');

  const sector = selectedOptionText(sectorSelect);

  const apiPayload = {
    type: 'contact',
    name,
    email,
    company,
    company_registration: cnpj || null,
    phone,
    sector: sectorSelect?.value || '',
    message,
    language: currentLang,
    source_page: 'home',
    website: ''
  };

  await handleLeadFormSubmission({
    form,
    apiPayload,
    successElement:
      document.getElementById('contactSuccess'),
    fallbackErrorMessage:
      'Falha ao enviar o formulário.',
    consoleErrorMessage:
      'Erro ao enviar formulário de contato:',
    alertMessage:
      'Não foi possível enviar sua mensagem. Verifique sua conexão e tente novamente.'
  });
}

async function submitSampleRequestForm(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const name = readTrimmedInput('modalName');
  const email = readTrimmedInput('modalEmail');
  const company = readTrimmedInput('modalCompany');
  const cnpj = readTrimmedInput('modalCnpj');
  const location = readTrimmedInput('modalLocation');
  const phone = readTrimmedInput('modalPhone');
  const message = readTrimmedInput('modalMessage');

  const apiPayload = {
    type: 'sample_request',
    name,
    email,
    company,
    company_registration: cnpj,
    phone,
    location,
    message,
    language: currentLang,
    source_page: 'home',
    website: ''
  };

  await handleLeadFormSubmission({
    form,
    apiPayload,
    successElement:
      document.getElementById('modalSuccess'),
    successText:
      translations[currentLang]?.form_success ||
      'Solicitação enviada com sucesso! Nossa equipe entrará em contato.',
    fallbackErrorMessage:
      'Falha ao enviar a solicitação.',
    consoleErrorMessage:
      'Erro ao enviar solicitação de amostra:',
    alertMessage:
      'Não foi possível enviar a solicitação. Verifique sua conexão e tente novamente.'
  });
}
// WhatsApp Floating Button & Lead Form Logic
function initLeadInputMasks() {
  const phoneInputIds = [
    'wa_lead_phone',
    'contact_phone',
    'modalPhone'
  ];

  const companyRegistrationInputIds = [
    'wa_lead_cnpj',
    'contact_cnpj',
    'modalCnpj'
  ];

  phoneInputIds.forEach(inputId => {
    registerInputFormatter(inputId, formatPhone);
  });

  companyRegistrationInputIds.forEach(inputId => {
    registerInputFormatter(
      inputId,
      formatCompanyRegistration
    );
  });
}

function registerInputFormatter(inputId, formatter) {
  const input = document.getElementById(inputId);

  input?.addEventListener('input', event => {
    event.target.value = formatter(event.target.value);
  });
}

function formatPhone(value) {
  if (currentLang !== 'pt') {
    return value
      .replace(/[^\d+()\s-]/g, '')
      .replace(/(?!^)\+/g, '')
      .slice(0, 25);
  }

  const digits = value.replace(/\D/g, '').slice(0, 11);

  if (digits.length > 10) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
  }

  if (digits.length > 6) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
  }

  if (digits.length > 2) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
  }

  return digits.length > 0 ? `(${digits}` : '';
}

function formatCompanyRegistration(value) {
  if (currentLang !== 'pt') {
    return value;
  }

  const digits = value.replace(/\D/g, '').slice(0, 14);

  if (digits.length > 12) {
    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
  }

  if (digits.length > 8) {
    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8)}`;
  }

  if (digits.length > 5) {
    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5)}`;
  }

  if (digits.length > 2) {
    return `${digits.slice(0, 2)}.${digits.slice(2)}`;
  }

  return digits;
}

function toggleWhatsappLeadModal(open) {
  if (open) {
    openLeadModal('whatsappLeadModal');
  } else {
    closeLeadModal(document.getElementById('whatsappLeadModal'));
  }
}

async function submitWhatsappLead(event) {
  event.preventDefault();

  const form = event.currentTarget;

  const apiPayload = {
    type: 'whatsapp',
    name: readTrimmedInput('wa_lead_name'),
    email: readTrimmedInput('wa_lead_email'),
    company: readTrimmedInput('wa_lead_company'),
    company_registration:
      readTrimmedInput('wa_lead_cnpj'),
    phone: readTrimmedInput('wa_lead_phone'),
    quantity: readTrimmedInput('wa_lead_qty'),
    language: currentLang,
    source_page: 'home',
    website: ''
  };

  const submitButton =
    form.querySelector('button[type="submit"]');

  const originalButtonText =
    submitButton?.textContent.trim() || '';

  setSubmitButtonState(
    submitButton,
    true,
    originalButtonText
  );

  try {
    await submitLeadRequest(
      apiPayload,
      'Falha ao registrar o lead.'
    );

    if (USE_LOCAL_LEAD_API) {
      alert(
        'Lead registrado localmente. A abertura do WhatsApp está desativada nesta demonstração.'
      );
    } else {
      alert(
        'Demonstração concluída. Nenhum dado foi transmitido.'
      );
    }

    toggleWhatsappLeadModal(false);
    form.reset();
  } catch (error) {
    console.error(
      'Erro ao registrar lead demonstrativo:',
      error
    );

    alert(
      'Não foi possível concluir a demonstração. Tente novamente.'
    );
  } finally {
    setSubmitButtonState(
      submitButton,
      false,
      originalButtonText
    );
  }
}