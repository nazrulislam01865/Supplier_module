/* Native Product workspace interactions; no iframe or API credentials in the browser. */
(() => {
  'use strict';
  document.querySelectorAll('[data-native-matrix]').forEach((matrix) => {
    const table = matrix.querySelector('table');
    const cells = () => [...table.querySelectorAll('tr')].map((row) => [...row.querySelectorAll('.ft-native-tier-cell')]);
    const rename = () => {
      cells().forEach(row => row.forEach((cell, i) => {
        cell.querySelectorAll('input').forEach(input => {
          input.name = input.name.replace(/\[\d+\]/, `[${i}]`);
          input.setAttribute('aria-label', `${input.name} column ${i + 1}`);
        });
      }));
    };
    matrix.querySelector('.ft-native-add-tier')?.addEventListener('click', () => {
      const columns = cells();
      if (!columns[0] || columns[0].length >= 100) return;
      columns.forEach((row) => {
        const clone = row[row.length - 1].cloneNode(true);
        clone.querySelectorAll('input').forEach(input => input.value = '');
        row[row.length - 1].parentElement.append(clone);
      });
      rename();
    });
    matrix.querySelector('.ft-native-remove-tier')?.addEventListener('click', () => {
      const columns = cells();
      if (!columns[0] || columns[0].length <= 1) {
        columns.forEach(row => row[0]?.querySelectorAll('input').forEach(input => input.value = ''));
        return;
      }
      columns.forEach(row => row[row.length - 1].remove());
      rename();
    });
  });
  document.querySelectorAll('[data-repeat]').forEach((repeat) => {
    const list = repeat.querySelector('.ft-native-repeat-items');
    const template = repeat.querySelector('template');
    repeat.querySelector('[data-native-add-row]')?.addEventListener('click', () => {
      if (list.children.length >= (repeat.dataset.repeat === 'options' ? 50 : 30)) return;
      const content = template.innerHTML.replaceAll('__INDEX__', String(list.children.length));
      list.insertAdjacentHTML('beforeend', content);
    });
    repeat.addEventListener('click', (event) => {
      if (!(event.target instanceof Element) || !event.target.closest('[data-native-remove-row]')) return;
      event.target.closest('.ft-native-repeat-row')?.remove();
      [...list.children].forEach((row, index) => {
        row.querySelectorAll('[name]').forEach((field) => field.name = field.name.replace(/\[\d+\]/, `[${index}]`));
      });
    });
  });
  document.querySelectorAll('[data-native-copy]').forEach((button) => {
    button.addEventListener('click', () => {
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(button.dataset.nativeCopy || '').catch(() => {});
      }
    });
  });
  // Product image upload is a separate, signed API operation. This drop zone
  // only selects a local file; submitting still requires explicit user action.
  // The availability fields are owned by a separate API form, even when
  // displayed within the Product information section.
  document.querySelectorAll('[data-native-availability]').forEach((scope) => {
    const radios = [...scope.querySelectorAll('input[name="mode"]')];
    const clients = scope.querySelector('.ft-native-availability-client-list');
    if (!clients || radios.length === 0) return;
    const update = () => { clients.hidden = !radios.some(r => r.value === 'specific' && r.checked); };
    radios.forEach(r => r.addEventListener('change', update));
    update();
  });
  const imageDrop = document.querySelector('[data-native-image-drop]');
  const imageFile = document.getElementById('supplierNativeImageFile');
  if (imageDrop && imageFile) {
    imageDrop.tabIndex = 0;
    imageDrop.setAttribute('role', 'button');
    imageDrop.setAttribute('aria-label', 'Choose a product image');
    imageDrop.addEventListener('click', () => imageFile.click());
    imageDrop.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); imageFile.click(); }
    });
    for (const eventName of ['dragenter', 'dragover']) {
      imageDrop.addEventListener(eventName, event => {
        event.preventDefault(); imageDrop.classList.add('is-dragging');
      });
    }
    for (const eventName of ['dragleave', 'drop']) {
      imageDrop.addEventListener(eventName, event => {
        event.preventDefault(); imageDrop.classList.remove('is-dragging');
      });
    }
    imageDrop.addEventListener('drop', event => {
      const image = [...(event.dataTransfer?.files || [])][0];
      if (!image || !['image/png', 'image/jpeg', 'image/webp'].includes(image.type) || image.size > 5 * 1024 * 1024) return;
      imageFile.files = event.dataTransfer.files;
      imageFile.dispatchEvent(new Event('change', { bubbles: true }));
    });
    imageFile.addEventListener('change', () => {
      const file = imageFile.files?.[0];
      if (!file || !['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) return;
      const label = imageDrop.querySelector('strong');
      if (label) label.textContent = file.name;
    });
  }
  // Document uploads use the existing signed API form. A selected file and an
  // explicit click on Upload/Replace are both required; selecting never uploads.
  document.querySelectorAll('[data-native-document-upload]').forEach((form) => {
    const input = form.querySelector('input[type="file"]');
    const picker = form.querySelector('[data-native-document-drop]');
    const filename = form.querySelector('[data-native-file-name]');
    const submit = form.querySelector('[data-native-file-submit]');
    if (!input || !picker || !filename || !submit) return;
    const supported = /\.(pdf|docx|png|jpe?g)$/i;
    const refresh = () => {
      const file = input.files?.[0];
      const valid = !!file && file.size <= 5 * 1024 * 1024 && supported.test(file.name);
      submit.disabled = !valid;
      picker.classList.toggle('is-invalid', !!file && !valid);
      filename.textContent = !file ? 'No file selected'
        : !supported.test(file.name) ? 'Use PDF, DOCX, PNG or JPG'
        : file.size > 5 * 1024 * 1024 ? 'File exceeds the 5 MB limit'
        : `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
    };
    input.addEventListener('change', refresh);
    ['dragenter', 'dragover'].forEach((eventName) => picker.addEventListener(eventName, (event) => {
      event.preventDefault();
      picker.classList.add('is-dragging');
    }));
    ['dragleave', 'drop'].forEach((eventName) => picker.addEventListener(eventName, (event) => {
      event.preventDefault();
      picker.classList.remove('is-dragging');
    }));
    picker.addEventListener('drop', (event) => {
      if (!event.dataTransfer?.files?.length) return;
      try {
        input.files = event.dataTransfer.files;
        refresh();
      } catch (_) {
        filename.textContent = 'Select this file with Choose file instead.';
      }
    });
    refresh();
  });
  const mainCategory = document.querySelector('#nativeProductMainCategory');
  const productCategory = document.querySelector('#nativeProductCategory');
  if (mainCategory && productCategory) {
    const refresh = () => {
      const mainId = mainCategory.value;
      [...productCategory.options].forEach((option) => {
        if (option.value === '') return;
        const hidden = mainId !== '' && option.dataset.mainId !== mainId;
        option.hidden = hidden;
        option.disabled = hidden;
      });
      if (productCategory.selectedOptions[0]?.disabled) productCategory.value = '';
    };
    mainCategory.addEventListener('change', refresh);
    refresh();
  }
  const level = document.querySelector('#nativeCategoryLevel');
  const parent = document.querySelector('#nativeCategoryParent');
  if (level && parent) {
    const update = () => {
      const needed = level.value === 'product_subcategory' ? 'product_category'
        : (level.value === 'product_category' ? 'product_main_category' : 'none');
      [...parent.options].forEach(option => {
        option.hidden = option.value !== '' && option.dataset.parentType !== needed;
        option.disabled = option.hidden;
      });
      parent.disabled = needed === 'none';
      if (parent.selectedOptions[0]?.disabled) parent.value = '';
    };
    level.addEventListener('change', update);
    update();
  }
})();
