// LeadFlow Industrial - Application Logic & Dynamic Features


let currentLang = 'pt';

document.addEventListener('DOMContentLoaded', () => {
  initLanguage();
  initHeaderScroll();
  initMobileMenu();
  initModal();
  initForm();
  initWhatsappLead();
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

// Accessible Technical Sample Modal
let lastSampleModalTrigger = null;

function getSampleModalFocusableElements(modal) {
  const selector = [
    "a[href]",
    "button:not([disabled])",
    "input:not([disabled])",
    "select:not([disabled])",
    "textarea:not([disabled])",
    '[tabindex]:not([tabindex="-1"])'
  ].join(",");

  return Array.from(modal.querySelectorAll(selector)).filter(
    (element) =>
      !element.hidden &&
      element.getAttribute("aria-hidden") !== "true"
  );
}

function closeSampleModal({ restoreFocus = true } = {}) {
  const modal = document.getElementById("sampleModal");

  if (!modal || !modal.classList.contains("open")) {
    return;
  }

  modal.classList.remove("open");
  modal.setAttribute("aria-hidden", "true");
  modal.inert = true;

  document.body.classList.remove("modal-open");

  if (restoreFocus) {
    const triggerIsVisible =
      lastSampleModalTrigger &&
      lastSampleModalTrigger.isConnected &&
      lastSampleModalTrigger.getClientRects().length > 0;

    const focusTarget = triggerIsVisible
      ? lastSampleModalTrigger
      : document.getElementById("mobileToggle");

    focusTarget?.focus();
  }

  lastSampleModalTrigger = null;
}

function initModal() {
  const modal = document.getElementById("sampleModal");
  const closeButton = document.getElementById("modalCloseBtn");
  const modalCard = modal?.querySelector(".modal-card");
  const modalTitle = modal?.querySelector("h3");
  const modalDescription = modal?.querySelector("p");

  if (!modal || !modalCard) {
    return;
  }

  if (modalTitle) {
    modalTitle.id = "sampleModalTitle";
    modal.setAttribute(
      "aria-labelledby",
      "sampleModalTitle"
    );
  }

  if (modalDescription) {
    modalDescription.id = "sampleModalDescription";
    modal.setAttribute(
      "aria-describedby",
      "sampleModalDescription"
    );
  }

  modal.setAttribute("role", "dialog");
  modal.setAttribute("aria-modal", "true");
  modal.setAttribute("aria-hidden", "true");
  modal.inert = true;

  modalCard.setAttribute("tabindex", "-1");

  if (closeButton) {
    closeButton.type = "button";

    closeButton.addEventListener("click", () => {
      closeSampleModal();
    });
  }

  modal.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeSampleModal();
    }
  });

  modal.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      closeSampleModal();
      return;
    }

    if (event.key !== "Tab") {
      return;
    }

    const focusableElements =
      getSampleModalFocusableElements(modal);

    if (focusableElements.length === 0) {
      event.preventDefault();
      modalCard.focus();
      return;
    }

    const firstElement = focusableElements[0];
    const lastElement =
      focusableElements[focusableElements.length - 1];

    if (
      event.shiftKey &&
      document.activeElement === firstElement
    ) {
      event.preventDefault();
      lastElement.focus();
    } else if (
      !event.shiftKey &&
      document.activeElement === lastElement
    ) {
      event.preventDefault();
      firstElement.focus();
    }
  });
}

function openSampleModal(productName = "SLES 70%") {
  const modal = document.getElementById("sampleModal");
  const closeButton = document.getElementById("modalCloseBtn");
  const modalCard = modal?.querySelector(".modal-card");

  if (!modal) {
    return;
  }

  lastSampleModalTrigger =
    document.activeElement instanceof HTMLElement
      ? document.activeElement
      : null;

  modal.inert = false;
  modal.setAttribute("aria-hidden", "false");
  modal.classList.add("open");

  document.body.classList.add("modal-open");

  if (closeButton) {
    closeButton.setAttribute(
      "aria-label",
      translations[currentLang]?.modal_close || "Fechar"
    );
  }

  const modalSuccess =
    document.getElementById("modalSuccess");

  if (modalSuccess) {
    modalSuccess.style.display = "none";
  }

  window.requestAnimationFrame(() => {
    if (closeButton) {
      closeButton.focus();
    } else {
      modalCard?.focus();
    }
  });
}

// Target Email for all Contact Forms
const TARGET_CONTACT_EMAIL = 'contato@leadflow.example';

// Backend local utilizado somente durante o desenvolvimento
const LOCAL_LEAD_API_URL =
  'http://127.0.0.1:8000/api/leads';

const USE_LOCAL_LEAD_API = [
  '127.0.0.1',
  'localhost'
].includes(window.location.hostname);
function createPortfolioDemoResponse() {
  return {
    ok: true,
    json: async () => ({
      success: true,
      demo: true
    })
  };
}

// Form Submission
function initForm() {
  const contactForm = document.getElementById('contactForm');
  const modalForm = document.getElementById('modalForm');

  if (contactForm) {
    contactForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const name = document.getElementById('contact_name')?.value.trim() || '';
      const email = document.getElementById('contact_email')?.value.trim() || '';
      const company = document.getElementById('contact_company')?.value.trim() || '';
      const cnpj = document.getElementById('contact_cnpj')?.value.trim() || '';
      const phone = document.getElementById('contact_phone')?.value.trim() || '';

      const sectorSelect = document.getElementById('contact_sector');
      const sector = sectorSelect?.selectedOptions[0]?.textContent.trim() || '';

      const message = document.getElementById('contact_message')?.value.trim() || '';

      const payload = {
        "_subject": `Novo Contato pelo Site - LeadFlow Industrial (${name})`,
        "_replyto": email,
        "_to": TARGET_CONTACT_EMAIL,

        "Nome Completo": name,
        "Empresa": company,
        "email": email,
        "CNPJ / Registro da Empresa": cnpj,
        "Telefone / WhatsApp": phone,
        "Setor": sector,
        "Mensagem": message
      };

      const leadApiPayload = {
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

      // Direct email notification to contato@leadflow.example via FormSubmit AJAX
      const submitButton = contactForm.querySelector('button[type="submit"]');
      const successMsg = document.getElementById('contactSuccess');
      const originalButtonText = submitButton?.textContent || '';

      if (successMsg) {
        successMsg.style.display = 'none';
      }

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent =
          translations[currentLang]?.form_sending || "Enviando...";
      }

      try {
        const response = USE_LOCAL_LEAD_API
          ? await fetch(LOCAL_LEAD_API_URL, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json'
            },
            body: JSON.stringify(leadApiPayload)
          })
          : createPortfolioDemoResponse();

        const data = await response.json();

        if (
          !response.ok ||
          (
            !USE_LOCAL_LEAD_API &&
            (
              data.success === false ||
              data.success === 'false'
            )
          )
        ) {
          throw new Error(
            data.message || 'Falha ao enviar o formulário.'
          );
        }

        if (successMsg) {
          successMsg.style.display = 'block';
        }

        contactForm.reset();
      } catch (error) {
        console.error('Erro ao enviar formulário de contato:', error);

        alert(
          'Não foi possível enviar sua mensagem. Verifique sua conexão e tente novamente.'
        );
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalButtonText;
        }
      }
    });
  }

  if (modalForm) {
    modalForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const name = document.getElementById('modalName')?.value.trim() || '';
      const email = document.getElementById('modalEmail')?.value.trim() || '';
      const company = document.getElementById('modalCompany')?.value.trim() || '';
      const cnpj = document.getElementById('modalCnpj')?.value.trim() || '';
      const location = document.getElementById('modalLocation')?.value.trim() || '';
      const phone = document.getElementById('modalPhone')?.value.trim() || '';
      const message = document.getElementById('modalMessage')?.value.trim() || '';

      const modalSuccess = document.getElementById('modalSuccess');
      const submitButton = modalForm.querySelector('button[type="submit"]');
      const originalButtonText = submitButton?.textContent.trim() || '';

      if (modalSuccess) {
        modalSuccess.style.display = 'none';
      }

      const payload = {
        "_subject": `Solicitação de Amostra Técnica SLES 70% - LeadFlow Industrial (${name})`,
        "_replyto": email,
        "_to": TARGET_CONTACT_EMAIL,
        "Nome Completo": name,
        "Empresa": company,
        "CNPJ / Registro da Empresa": cnpj,
        "Cidade / Estado / País": location,
        "email": email,
        "Telefone / WhatsApp": phone,
        "Especificações / Amostra": message
      };

      const sampleApiPayload = {
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

      try {
        if (submitButton) {
          submitButton.disabled = true;
          submitButton.textContent =
            translations[currentLang]?.form_sending || 'Enviando...';
        }

        const response = USE_LOCAL_LEAD_API
          ? await fetch(LOCAL_LEAD_API_URL, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json'
            },
            body: JSON.stringify(sampleApiPayload)
          })
          : createPortfolioDemoResponse();

        const data = await response.json();

        if (
          !response.ok ||
          (
            !USE_LOCAL_LEAD_API &&
            (
              data.success === false ||
              data.success === 'false'
            )
          )
        ) {
          throw new Error(
            data.message || 'Falha ao enviar a solicitação.'
          );
        }

        modalForm.reset();

        if (modalSuccess) {
          modalSuccess.textContent =
            translations[currentLang]?.form_success ||
            'Solicitação enviada com sucesso! Nossa equipe entrará em contato.';

          modalSuccess.style.display = 'block';
        }
      } catch (error) {
        console.error('Erro ao enviar solicitação de amostra:', error);

        alert(
          'Não foi possível enviar a solicitação. Verifique sua conexão e tente novamente.'
        );
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalButtonText;
        }
      }
    });
  }
}
// Configurable WhatsApp Target Number (Change this to your actual corporate number)
const WHATSAPP_TARGET_NUMBER = '5500000000000';

// WhatsApp Floating Button & Lead Form Logic
function initWhatsappLead() {
  const phoneSelectors = ['wa_lead_phone', 'contact_phone', 'modalPhone'];
  phoneSelectors.forEach(id => {
    const phoneInput = document.getElementById(id);
    if (phoneInput) {
      phoneInput.addEventListener('input', (e) => {
        if (
          (
            id === 'contact_phone' ||
            id === 'modalPhone' ||
            id === 'wa_lead_phone'
          ) &&
          currentLang !== 'pt'
        ) {
          let internationalPhone = e.target.value.replace(/[^\d+()\s-]/g, '');

          // Mantém o sinal de + somente no começo
          internationalPhone = internationalPhone.replace(/(?!^)\+/g, '');

          // Evita números excessivamente longos
          if (internationalPhone.length > 25) {
            internationalPhone = internationalPhone.substring(0, 25);
          }

          e.target.value = internationalPhone;
          return;
        }

        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.substring(0, 11);

        if (value.length > 10) {
          // Mobile layout: (XX) XXXXX-XXXX
          e.target.value = `(${value.substring(0, 2)}) ${value.substring(2, 7)}-${value.substring(7)}`;
        } else if (value.length > 6) {
          // Landline layout: (XX) XXXX-XXXX
          e.target.value = `(${value.substring(0, 2)}) ${value.substring(2, 6)}-${value.substring(6)}`;
        } else if (value.length > 2) {
          e.target.value = `(${value.substring(0, 2)}) ${value.substring(2)}`;
        } else if (value.length > 0) {
          e.target.value = `(${value}`;
        } else {
          e.target.value = '';
        }
      });
    }
  });

  const cnpjSelectors = ['wa_lead_cnpj', 'contact_cnpj', 'modalCnpj'];
  cnpjSelectors.forEach(id => {
    const cnpjInput = document.getElementById(id);
    if (cnpjInput) {
      cnpjInput.addEventListener('input', (e) => {
        if (
          (
            id === 'contact_cnpj' ||
            id === 'modalCnpj' ||
            id === 'wa_lead_cnpj'
          ) &&
          currentLang !== 'pt'
        ) {
          return;
        }
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 14) value = value.substring(0, 14);

        if (value.length > 12) {
          e.target.value = `${value.substring(0, 2)}.${value.substring(2, 5)}.${value.substring(5, 8)}/${value.substring(8, 12)}-${value.substring(12)}`;
        } else if (value.length > 8) {
          e.target.value = `${value.substring(0, 2)}.${value.substring(2, 5)}.${value.substring(5, 8)}/${value.substring(8)}`;
        } else if (value.length > 5) {
          e.target.value = `${value.substring(0, 2)}.${value.substring(2, 5)}.${value.substring(5)}`;
        } else if (value.length > 2) {
          e.target.value = `${value.substring(0, 2)}.${value.substring(2)}`;
        } else {
          e.target.value = value;
        }
      });
    }
  });
}

function openWhatsappLeadModal() {
  toggleWhatsappLeadModal(true);
}

function closeWhatsappLeadModal(e) {
  const modal = document.getElementById('whatsappLeadModal');
  if (e.target === modal) {
    toggleWhatsappLeadModal(false);
  }
}

function toggleWhatsappLeadModal(open) {
  const modal = document.getElementById('whatsappLeadModal');
  if (modal) {
    if (open) {
      modal.classList.add('open');
    } else {
      modal.classList.remove('open');
    }
  }
}

async function submitWhatsappLead(e) {
  e.preventDefault();

  const name = document.getElementById('wa_lead_name').value;
  const phone = document.getElementById('wa_lead_phone').value;
  const email = document.getElementById('wa_lead_email').value;
  const company = document.getElementById('wa_lead_company').value;
  const cnpj = document.getElementById('wa_lead_cnpj').value;
  const qty = document.getElementById('wa_lead_qty').value;

  const whatsappApiPayload = {
    type: 'whatsapp',
    name,
    email,
    company,
    company_registration: cnpj,
    phone,
    quantity: qty,
    language: currentLang,
    source_page: 'home',
    website: ''
  };

  const messagePt =
    `Olá, gostaria de falar com um especialista da LEADFLOW. Aqui estão meus dados:\n\n` +
    `• Nome: ${name}\n` +
    `• Empresa: ${company}\n` +
    `• CNPJ: ${cnpj}\n` +
    `• Telefone / WhatsApp: ${phone}\n` +
    `• E-mail: ${email}\n` +
    `• Quantidade desejada de SLES 70%: ${qty}`;

  const messageInternational =
    `Hello, I would like to speak with a LEADFLOW specialist. Here are my details:\n\n` +
    `• Name: ${name}\n` +
    `• Company: ${company}\n` +
    `• Tax ID / Registration No.: ${cnpj}\n` +
    `• Phone / WhatsApp: ${phone}\n` +
    `• Email: ${email}\n` +
    `• Desired SLES 70% quantity: ${qty}`;

  const messageText =
    currentLang === 'pt'
      ? messagePt
      : messageInternational;

  const encodedText = encodeURIComponent(messageText);

  if (USE_LOCAL_LEAD_API) {
    try {
      const response = await fetch(
        LOCAL_LEAD_API_URL,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json'
          },
          body: JSON.stringify(whatsappApiPayload)
        }
      );

      const data = await response.json();

      if (!response.ok) {
        throw new Error(
          data.message || 'Falha ao registrar o lead.'
        );
      }
    } catch (error) {
      console.error(
        'Erro ao registrar lead do WhatsApp:',
        error
      );

      alert(
        'Não foi possível registrar seus dados. Tente novamente.'
      );

      return;
    }
  }
  if (USE_LOCAL_LEAD_API) {
    alert(
      'Lead registrado localmente. A abertura do WhatsApp está desativada nesta demonstração.'
    );
  } else {
    alert(
      'Demonstração concluída. Nenhum dado foi transmitido.'
    );
  }

  // Close the modal and reset the form
  toggleWhatsappLeadModal(false);
  document.getElementById('whatsappLeadForm').reset();
}
