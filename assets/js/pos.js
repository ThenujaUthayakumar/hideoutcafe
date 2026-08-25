/**
 * The Hide Out Cafe - Main Point of Sale Interactive Engine
 */

const PosApp = {
  state: {
    cart: [],
    orderType: 'dine_in',
    tableId: null,
    tableName: 'No Table',
    customer: null,
    discountType: 'percentage',
    discountValue: 0,
    currencySymbol: 'Rs.',
    activeCategoryId: 'all',
    searchQuery: '',
    selectedProductForModal: null
  },

  init(settings = {}) {
    this.state.currencySymbol = settings.currencySymbol || 'Rs.';
    
    this.bindKeyboardShortcuts();
    this.renderCart();
    this.filterProducts();
  },

  bindKeyboardShortcuts() {
    window.addEventListener('keydown', (e) => {
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) && e.key !== 'F2' && e.key !== 'Escape') {
        return;
      }
      
      switch (e.key) {
        case 'F1':
          e.preventDefault();
          const search = document.getElementById('pos-search-input');
          if (search) search.focus();
          break;
        case 'F2':
          e.preventDefault();
          if (this.state.cart.length > 0) this.openCheckoutModal();
          break;
        case 'F4':
          e.preventDefault();
          this.holdCurrentOrder();
          break;
        case 'F9':
          e.preventDefault();
          this.confirmClearCart();
          break;
        case 'Escape':
          this.closeAllModals();
          break;
      }
    });
  },

  // Category Filtering
  setCategory(catId) {
    this.state.activeCategoryId = catId;
    document.querySelectorAll('.cat-pill').forEach(btn => {
      if (btn.dataset.category == catId) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });
    this.filterProducts();
  },

  filterProducts() {
    const query = this.state.searchQuery.toLowerCase().trim();
    const cat = this.state.activeCategoryId;
    const cards = document.querySelectorAll('.pos-product-card');

    let visibleCount = 0;
    cards.forEach(card => {
      const cardCat = card.dataset.category;
      const cardName = (card.dataset.name || '').toLowerCase();
      const cardCode = (card.dataset.code || '').toLowerCase();

      const matchesCat = (cat === 'all' || cardCat == cat);
      const matchesQuery = (query === '' || cardName.includes(query) || cardCode.includes(query));

      if (matchesCat && matchesQuery) {
        card.classList.remove('hidden');
        visibleCount++;
      } else {
        card.classList.add('hidden');
      }
    });

    const emptyState = document.getElementById('pos-empty-products');
    if (emptyState) {
      emptyState.classList.toggle('hidden', visibleCount > 0);
    }
  },

  onSearch(query) {
    this.state.searchQuery = query;
    this.filterProducts();
  },

  selectProduct(productId) {
    const rawData = document.getElementById(`product-raw-${productId}`);
    if (!rawData) return;
    
    try {
      const product = JSON.parse(rawData.textContent);
      
      const hasVariants = product.variants && product.variants.length > 0;
      const hasModifiers = window.POS_MODIFIERS && window.POS_MODIFIERS.length > 0;

      if (hasVariants || hasModifiers) {
        this.openModifierModal(product);
      } else {
        this.addItemToCart({
          productId: product.id,
          name: product.name,
          variantName: null,
          unitPrice: parseFloat(product.price),
          modifiers: [],
          notes: '',
          quantity: 1
        });
        SoundFX.click();
      }
    } catch (e) {
      console.error("Failed to parse product data", e);
    }
  },

  openModifierModal(product) {
    this.state.selectedProductForModal = product;
    
    document.getElementById('modal-product-name').textContent = product.name;
    document.getElementById('modal-product-price').textContent = `${this.state.currencySymbol} ${parseFloat(product.price).toFixed(2)}`;
    document.getElementById('modal-item-notes').value = '';

    // Render Variants
    const variantContainer = document.getElementById('modal-variants-list');
    if (variantContainer) {
      variantContainer.innerHTML = '';
      if (product.variants && product.variants.length > 0) {
        document.getElementById('modal-variants-section').classList.remove('hidden');
        product.variants.forEach((v, idx) => {
          const isFirst = idx === 0;
          const extraText = parseFloat(v.extra_price) > 0 ? ` (+${this.state.currencySymbol} ${parseFloat(v.extra_price).toFixed(2)})` : '';
          variantContainer.innerHTML += `
            <label class="flex items-center justify-between p-3 border rounded-xl cursor-pointer hover:border-red-600 transition bg-stone-50 has-[:checked]:bg-red-50 has-[:checked]:border-red-600">
              <div class="flex items-center gap-3">
                <input type="radio" name="modal_variant" value="${v.id}" data-name="${v.variant_name}" data-price="${v.extra_price}" ${isFirst ? 'checked' : ''} class="w-4 h-4 text-red-600 focus:ring-red-600">
                <span class="font-medium text-stone-800">${v.variant_name}</span>
              </div>
              <span class="text-sm font-semibold text-red-700">${extraText}</span>
            </label>
          `;
        });
      } else {
        document.getElementById('modal-variants-section').classList.add('hidden');
      }
    }

    // Render Modifiers
    const modContainer = document.getElementById('modal-modifiers-list');
    if (modContainer && window.POS_MODIFIERS) {
      modContainer.innerHTML = '';
      window.POS_MODIFIERS.forEach(mod => {
        const priceText = parseFloat(mod.price) > 0 ? `+${this.state.currencySymbol} ${parseFloat(mod.price).toFixed(2)}` : 'Free';
        modContainer.innerHTML += `
          <label class="flex items-center justify-between p-2.5 border rounded-xl cursor-pointer hover:border-red-600 transition bg-white text-xs has-[:checked]:bg-red-50 has-[:checked]:border-red-600">
            <div class="flex items-center gap-2">
              <input type="checkbox" name="modal_modifier" value="${mod.id}" data-name="${mod.name}" data-price="${mod.price}" class="w-4 h-4 text-red-600 rounded focus:ring-red-600">
              <span class="font-medium text-stone-800">${mod.name}</span>
            </div>
            <span class="font-bold text-red-700">${priceText}</span>
          </label>
        `;
      });
    }

    openModal('product-modifier-modal');
  },

  confirmModifierSelection() {
    const product = this.state.selectedProductForModal;
    if (!product) return;

    let basePrice = parseFloat(product.price);
    let variantName = null;

    const selectedVariant = document.querySelector('input[name="modal_variant"]:checked');
    if (selectedVariant) {
      variantName = selectedVariant.dataset.name;
      basePrice += parseFloat(selectedVariant.dataset.price || 0);
    }

    const selectedModifiers = [];
    document.querySelectorAll('input[name="modal_modifier"]:checked').forEach(chk => {
      selectedModifiers.push({
        id: chk.value,
        name: chk.dataset.name,
        price: parseFloat(chk.dataset.price || 0)
      });
    });

    const notes = document.getElementById('modal-item-notes').value.trim();

    this.addItemToCart({
      productId: product.id,
      name: product.name,
      variantName: variantName,
      unitPrice: basePrice,
      modifiers: selectedModifiers,
      notes: notes,
      quantity: 1
    });

    closeModal('product-modifier-modal');
    SoundFX.click();
  },

  addItemToCart(item) {
    const modSig = (item.modifiers || []).map(m => m.name).sort().join('|');
    const signature = `${item.productId}_${item.variantName || 'default'}_${modSig}_${item.notes || ''}`;

    const existingIndex = this.state.cart.findIndex(c => c.signature === signature);
    if (existingIndex > -1) {
      this.state.cart[existingIndex].quantity += 1;
    } else {
      item.signature = signature;
      this.state.cart.push(item);
    }

    this.renderCart();
    Toast.success(`Added ${item.name} to cart`);
  },

  updateQuantity(index, delta) {
    if (!this.state.cart[index]) return;
    this.state.cart[index].quantity += delta;
    if (this.state.cart[index].quantity <= 0) {
      this.state.cart.splice(index, 1);
      SoundFX.error();
    } else {
      SoundFX.click();
    }
    this.renderCart();
  },

  removeItem(index) {
    if (!this.state.cart[index]) return;
    this.state.cart.splice(index, 1);
    SoundFX.error();
    this.renderCart();
  },

  confirmClearCart() {
    if (this.state.cart.length === 0) return;
    confirmAction('Clear Cart?', 'Are you sure you want to empty all items from the current order?', 'Yes, Clear All').then(res => {
      if (res.isConfirmed) {
        this.clearCart();
        Toast.info('Cart cleared');
      }
    });
  },

  clearCart() {
    this.state.cart = [];
    this.state.discountValue = 0;
    this.state.tableId = null;
    this.state.tableName = 'No Table';
    this.state.customer = { id: 1, name: 'Walk-in Customer', phone: '0000000000', points: 0 };
    this.renderCart();
  },

  setOrderType(type) {
    this.state.orderType = type;
    document.querySelectorAll('.order-type-btn').forEach(b => {
      if (b.dataset.type === type) {
        b.className = 'order-type-btn flex-1 py-2 px-3 rounded-lg text-xs font-bold uppercase transition bg-red-600 text-white shadow-md';
      } else {
        b.className = 'order-type-btn flex-1 py-2 px-3 rounded-lg text-xs font-bold uppercase transition bg-stone-100 text-stone-600 hover:bg-stone-200';
      }
    });

    if (type === 'dine_in') {
      document.getElementById('pos-table-selector-btn').classList.remove('hidden');
    } else {
      document.getElementById('pos-table-selector-btn').classList.add('hidden');
      this.state.tableId = null;
      this.state.tableName = 'No Table';
      document.getElementById('pos-selected-table-label').textContent = 'No Table';
    }

    this.renderCart();
  },

  selectTable(tableId, tableName) {
    this.state.tableId = tableId;
    this.state.tableName = tableName;
    document.getElementById('pos-selected-table-label').textContent = tableName;
    closeModal('table-selection-modal');
    Toast.success(`Table assigned: ${tableName}`);
  },

  selectCustomer(customer) {
    this.state.customer = customer;
    document.getElementById('pos-customer-name').textContent = customer.name;
    document.getElementById('pos-customer-points').textContent = `${Number(customer.points || 0).toFixed(2)} pts`;
    closeModal('customer-search-modal');
    Toast.success(`Customer set: ${customer.name}`);
  },

getCalculations() {
  let subtotal = 0;

  this.state.cart.forEach(item => {
    let itemUnit = parseFloat(item.unitPrice) || 0;

    if (item.modifiers && item.modifiers.length > 0) {
      item.modifiers.forEach(m => {
        itemUnit += parseFloat(m.price || 0);
      });
    }

    subtotal += itemUnit * item.quantity;
  });

  let discount = 0;

  if (this.state.discountType === 'percentage') {
    discount = (subtotal * (parseFloat(this.state.discountValue) || 0)) / 100;
  } else {
    discount = parseFloat(this.state.discountValue) || 0;
  }

  // Discount cannot exceed subtotal
  if (discount > subtotal) {
    discount = subtotal;
  }

  const grandTotal = Math.max(0, subtotal - discount);

  return {
    subtotal,
    discount,
    grandTotal
  };
},

  renderCart() {
    const cartContainer = document.getElementById('pos-cart-items');
    const badge = document.getElementById('pos-cart-count');
    const calc = this.getCalculations();

    if (badge) badge.textContent = this.state.cart.reduce((sum, item) => sum + item.quantity, 0);

    if (!cartContainer) return;

    if (this.state.cart.length === 0) {
      cartContainer.innerHTML = `
        <div class="flex flex-col items-center justify-center h-full py-16 text-stone-400">
          <i class="fa-solid fa-mug-saucer text-5xl mb-3 text-stone-300"></i>
          <p class="text-sm font-semibold">Your cart is empty</p>
          <p class="text-xs text-stone-400 mt-1">Tap items on the left to start order</p>
        </div>
      `;
    } else {
      cartContainer.innerHTML = '';
      this.state.cart.forEach((item, index) => {
        let modTotal = 0;
        let modsBadges = '';
        if (item.modifiers && item.modifiers.length > 0) {
          modsBadges = item.modifiers.map(m => {
            modTotal += parseFloat(m.price || 0);
            return `<span class="inline-block bg-red-100 text-red-900 text-[10px] px-1.5 py-0.5 rounded font-medium mr-1 mb-1">+ ${m.name}</span>`;
          }).join('');
        }

        const itemTotal = (item.unitPrice + modTotal) * item.quantity;
        const variantHtml = item.variantName ? `<span class="text-xs text-stone-500 font-normal">(${item.variantName})</span>` : '';
        const notesHtml = item.notes ? `<p class="text-[11px] text-red-600 italic mt-0.5"><i class="fa-regular fa-note-sticky mr-1"></i>${item.notes}</p>` : '';

        cartContainer.innerHTML += `
          <div class="flex items-start justify-between p-3 border-b border-stone-100 hover:bg-stone-50 transition group">
            <div class="flex-1 pr-2">
              <h4 class="font-semibold text-stone-800 text-sm leading-tight">${item.name} ${variantHtml}</h4>
              <p class="text-xs font-bold text-red-600 mt-0.5">${this.state.currencySymbol} ${item.unitPrice.toFixed(2)}</p>
              ${modsBadges ? `<div class="mt-1.5 flex flex-wrap">${modsBadges}</div>` : ''}
              ${notesHtml}
            </div>

            <div class="flex flex-col items-end gap-1.5">
              <span class="font-bold text-stone-900 text-sm">${this.state.currencySymbol} ${itemTotal.toFixed(2)}</span>
              <div class="flex items-center border border-stone-300 rounded-lg overflow-hidden bg-white shadow-xs">
                <button onclick="PosApp.updateQuantity(${index}, -1)" class="w-6 h-6 flex items-center justify-center text-stone-600 hover:bg-stone-100 active:bg-stone-200">
                  <i class="fa-solid fa-minus text-[10px]"></i>
                </button>
                <span class="w-6 text-center text-xs font-bold text-stone-800">${item.quantity}</span>
                <button onclick="PosApp.updateQuantity(${index}, 1)" class="w-6 h-6 flex items-center justify-center text-stone-600 hover:bg-stone-100 active:bg-stone-200">
                  <i class="fa-solid fa-plus text-[10px]"></i>
                </button>
              </div>
            </div>
          </div>
        `;
      });
    }

    // Update Summary Box
    document.getElementById('pos-subtotal').textContent = `${this.state.currencySymbol} ${calc.subtotal.toFixed(2)}`;
    document.getElementById('pos-discount').textContent = `-${this.state.currencySymbol} ${calc.discount.toFixed(2)}`;
    document.getElementById('pos-grand-total').textContent = `${this.state.currencySymbol} ${calc.grandTotal.toFixed(2)}`;
    
    // Bottom Pay Button
    const payBtnTotal = document.getElementById('pos-pay-btn-amount');
    if (payBtnTotal) payBtnTotal.textContent = `${this.state.currencySymbol} ${calc.grandTotal.toFixed(2)}`;
  },

  // Checkout & Payment
  openCheckoutModal() {
    if (this.state.cart.length === 0) {
      Toast.warning('Cart is empty. Please add items to checkout.');
      return;
    }

    const calc = this.getCalculations();
    document.getElementById('pay-modal-total').textContent = `${this.state.currencySymbol} ${calc.grandTotal.toFixed(2)}`;
    document.getElementById('pay-cash-tendered').value = calc.grandTotal.toFixed(2);
    
    this.calculateChange();
    this.renderPresetCashButtons(calc.grandTotal);
    
    openModal('pos-checkout-modal');
  },

  renderPresetCashButtons(total) {
    const presetsContainer = document.getElementById('pay-preset-buttons');
    if (!presetsContainer) return;

    // Sri Lanka Rupee standard notes: 500, 1000, 2000, 5000
    const rounded500 = Math.ceil(total / 500) * 500;
    const rounded1000 = Math.ceil(total / 1000) * 1000;
    const rounded2000 = Math.ceil(total / 2000) * 2000;
    const rounded5000 = Math.ceil(total / 5000) * 5000;
    
    const amounts = Array.from(new Set([total, rounded500, rounded1000, rounded2000, rounded5000, 5000])).filter(a => a >= total).slice(0, 4);

    presetsContainer.innerHTML = '';
    amounts.forEach(amt => {
      presetsContainer.innerHTML += `
        <button onclick="PosApp.setTenderedAmount(${amt.toFixed(2)})" class="py-2.5 px-3 bg-red-50 hover:bg-red-100 border border-red-200 text-red-900 font-bold rounded-xl text-sm transition">
          ${this.state.currencySymbol} ${amt.toFixed(0)}
        </button>
      `;
    });
  },

  setTenderedAmount(amount) {
    document.getElementById('pay-cash-tendered').value = amount;
    this.calculateChange();
  },

  calculateChange() {
    const calc = this.getCalculations();
    const tendered = parseFloat(document.getElementById('pay-cash-tendered').value) || 0;
    const change = Math.max(0, tendered - calc.grandTotal);
    
    document.getElementById('pay-change-amount').textContent = `${this.state.currencySymbol} ${change.toFixed(2)}`;
    
    const changeBox = document.getElementById('pay-change-box');
    if (tendered < calc.grandTotal) {
      changeBox.classList.add('bg-rose-50', 'border-rose-200');
      changeBox.classList.remove('bg-emerald-50', 'border-emerald-200');
      document.getElementById('pay-change-label').textContent = 'Shortage Amount:';
      document.getElementById('pay-change-amount').textContent = `-${this.state.currencySymbol} ${(calc.grandTotal - tendered).toFixed(2)}`;
      document.getElementById('pay-change-amount').className = 'text-2xl font-black text-rose-700';
    } else {
      changeBox.classList.remove('bg-rose-50', 'border-rose-200');
      changeBox.classList.add('bg-emerald-50', 'border-emerald-200');
      document.getElementById('pay-change-label').textContent = 'Change to Return:';
      document.getElementById('pay-change-amount').className = 'text-2xl font-black text-emerald-700';
    }
  },

  // Submit Order to Backend
  async submitOrder(paymentMethod = 'cash') {
    if (this.state.cart.length === 0) return;
    
    const calc = this.getCalculations();
    const tendered = parseFloat(document.getElementById('pay-cash-tendered').value) || calc.grandTotal;

    if (paymentMethod === 'cash' && tendered < calc.grandTotal) {
      Toast.error('Paid cash is less than the total bill amount!');
      return;
    }

    const payload = {
      order_type: this.state.orderType,
      table_id: this.state.tableId,
      customer_id: this.state.customer ? this.state.customer.id : null,
      payment_method: paymentMethod,
      paid_amount: tendered,
      change_amount: Math.max(0, tendered - calc.grandTotal),
      discount_type: this.state.discountType,
      discount_value: this.state.discountValue,
      discount_amount: calc.discount,
      subtotal: calc.subtotal,
      grand_total: calc.grandTotal,
      order_status: 'pending',
      notes: document.getElementById('pos-order-notes') ? document.getElementById('pos-order-notes').value : '',
      items: this.state.cart.map(i => ({
        product_id: i.productId,
        product_name: i.name,
        variant_name: i.variantName,
        unit_price: i.unitPrice,
        quantity: i.quantity,
        subtotal: (i.unitPrice + (i.modifiers || []).reduce((s, m) => s + parseFloat(m.price || 0), 0)) * i.quantity,
        modifiers: i.modifiers || [],
        notes: i.notes || ''
      }))
    };

    try {
      const submitBtn = document.getElementById('pay-confirm-btn');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-2"></i> Processing...`;
      }

      const res = await fetch('api/pos_checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const responseText = await res.text();
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (parseErr) {
        console.error("Server raw response:", responseText);
        throw new Error("Invalid response from server");
      }

      if (data.success) {
        SoundFX.success();
        closeModal('pos-checkout-modal');
        this.showReceiptModal(data.order);
        this.clearCart();
      } else {
        Toast.error(data.message || 'Transaction failed');
        SoundFX.error();
      }
    } catch (err) {
      console.error("Checkout error:", err);
      Toast.error('Error processing checkout: ' + err.message);
    } finally {
      const submitBtn = document.getElementById('pay-confirm-btn');
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<i class="fa-solid fa-check mr-2"></i> Complete Sale & Print`;
      }
    }
  },

  showReceiptModal(order) {
    const receiptFrame = document.getElementById('thermal-receipt-frame');
    if (receiptFrame) {
      receiptFrame.src = `print_receipt.php?id=${order.id}&embed=1`;
    }
    document.getElementById('receipt-invoice-no').textContent = order.invoice_no;
    openModal('receipt-success-modal');
  },

  printReceiptDirect(orderId) {
    const printWindow = window.open(`print_receipt.php?id=${orderId}&auto_print=1`, '_blank', 'width=400,height=600');
    if (printWindow) printWindow.focus();
  },

  async holdCurrentOrder() {
    if (this.state.cart.length === 0) {
      Toast.warning('Cannot hold an empty cart');
      return;
    }

    const { value: refName } = await Swal.fire({
      title: 'Hold / Park Order',
      input: 'text',
      inputLabel: 'Reference / Customer Name',
      inputValue: this.state.tableName !== 'No Table' ? this.state.tableName : (this.state.customer ? this.state.customer.name : 'Cart #' + Math.floor(Math.random() * 1000)),
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      confirmButtonText: 'Hold Order'
    });

    if (!refName) return;

    try {
      const res = await fetch('api/hold_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'hold',
          hold_reference: refName,
          customer_id: this.state.customer ? this.state.customer.id : null,
          customer_name: this.state.customer ? this.state.customer.name : 'Walk-in Customer',
          table_id: this.state.tableId,
          order_type: this.state.orderType,
          cart_json: JSON.stringify(this.state.cart),
          total_amount: this.getCalculations().grandTotal
        })
      });
      const data = await res.json();
      if (data.success) {
        Toast.success('Order held successfully');
        this.clearCart();
      }
    } catch (e) {
      Toast.error('Failed to hold order');
    }
  },

  async openHeldOrdersModal() {
    try {
      const res = await fetch('api/hold_order.php?action=list');
      const data = await res.json();
      const list = document.getElementById('held-orders-list');
      if (!list) return;

      if (!data.orders || data.orders.length === 0) {
        list.innerHTML = `<div class="p-8 text-center text-stone-400 font-medium">No held orders found</div>`;
      } else {
        list.innerHTML = '';
        data.orders.forEach(ho => {
          list.innerHTML += `
            <div class="flex items-center justify-between p-4 bg-white border border-stone-200 rounded-xl hover:border-red-600 transition shadow-xs">
              <div>
                <h4 class="font-bold text-stone-900">${ho.hold_reference}</h4>
                <p class="text-xs text-stone-500 mt-0.5">Held on: ${ho.created_at} | ${ho.order_type.toUpperCase()}</p>
                <span class="font-bold text-red-600 text-sm mt-1 inline-block">${this.state.currencySymbol} ${parseFloat(ho.total_amount).toFixed(2)}</span>
              </div>
              <div class="flex items-center gap-2">
                <button onclick="PosApp.recallHeldOrder(${ho.id})" class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold transition">
                  <i class="fa-solid fa-arrow-rotate-left mr-1"></i> Recall
                </button>
                <button onclick="PosApp.deleteHeldOrder(${ho.id})" class="p-2 text-rose-600 hover:bg-rose-50 rounded-lg text-xs transition">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </div>
          `;
        });
      }
      openModal('held-orders-modal');
    } catch (e) {
      Toast.error('Could not load held orders');
    }
  },

  async recallHeldOrder(heldId) {
    try {
      const res = await fetch(`api/hold_order.php?action=recall&id=${heldId}`);
      const data = await res.json();
      if (data.success) {
        this.state.cart = JSON.parse(data.order.cart_json);
        this.state.orderType = data.order.order_type || 'dine_in';
        this.state.tableId = data.order.table_id;
        this.setOrderType(this.state.orderType);
        this.renderCart();
        closeModal('held-orders-modal');
        Toast.success('Held order loaded');
      }
    } catch (e) {
      Toast.error('Failed to recall order');
    }
  },

  async deleteHeldOrder(heldId) {
    try {
      const res = await fetch(`api/hold_order.php?action=delete&id=${heldId}`);
      const data = await res.json();
      if (data.success) {
        Toast.info('Held order removed');
        this.openHeldOrdersModal();
      }
    } catch (e) {
      Toast.error('Failed to delete held order');
    }
  },

  closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach(modal => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    });
    document.body.classList.remove('overflow-hidden');
  }
};
