document.addEventListener('click', function (e) {
    var el = e.target;

    // close another
    var items = document.querySelectorAll('[data-another_click]');
    items.forEach(function (item) {
        if (el != item && !item.contains(el)) {
            item.classList.remove(item.dataset.another_click);
        }
    });

    // action
    for (var el = e.target; el && el != this; el = el.parentNode) {
        // methods for classes (dropdown, show, hide, active ..)
        if (
            el.matches('[data-toggle_class]')
            || el.matches('[data-add_class]')
            || el.matches('[data-remove_class]')
        ) {
            e.preventDefault();
            var target = el.dataset.target ? document.querySelector(el.dataset.target) : false;
            var targets = el.dataset.targets ? [...document.querySelectorAll(el.dataset.targets)] : [];
            if (!el.dataset.show_more) targets.push(el);
            if (target) targets.push(target);

            if (el.dataset.toggle_class) {
                targets.forEach(function (item) {
                    item.classList.toggle(el.dataset.toggle_class);
                });
            }
            if (el.dataset.add_class) {
                targets.forEach(function (item) {
                    item.classList.add(el.dataset.add_class);
                });
            }
            if (el.dataset.remove_class) {
                targets.forEach(function (item) {
                    item.classList.remove(el.dataset.remove_class);
                });
            }

            // set search input focus
            targets.forEach(function (item) {
                var input = item.querySelector('input');
                if (!input) {
                    input = item;
                }
                if (input.matches('[type="text"]')) {
                    setTimeout(() => {
                        input.focus();
                    }, 300);
                }
            });

            if (el.dataset.show_more) {
                if (el.innerText == el.dataset.show_more) {
                    el.innerText = el.dataset.data_text;
                } else {
                    el.dataset.data_text = el.innerText;
                    el.innerText = el.dataset.show_more;
                }
            }

            // iframe slot item
            if (
                el.matches('[data-toogle="slot_iframe"]')
                && target
            ) {
                var iframe = target.querySelector('iframe');
                if (iframe) {
                    iframe.src = iframe.dataset.src;
                }
            }

            return;
        }

        // ----
        // popup close
        if (el.matches('[data-popup-close]')) {
            e.preventDefault();
            let target = el.closest('[data-popup-wrap]');
            target.classList.remove('active');

            let urlData = el.dataset.popupClose;
            if (urlData !== '') {
                window.location.href = urlData;
            }

            var iframe = target.querySelector('iframe');
            if (iframe) {
                iframe.src = '';
            }
        }

        // Кнопка копирования промо-кода
        if (el.matches('[data-code_copy_target]')) {
            e.preventDefault();
            let btn = el;
            let input = document.querySelector(el.dataset.code_copy_target);
            if (input) {
                var promoCode = input.dataset.text ?? input.textContent;
                navigator.clipboard.writeText(promoCode).then(function () {
                    btn.dataset.text = btn.dataset.text ?? btn.textContent;
                    btn.textContent = btn.dataset.text_replace ?? 'Сopied';
                    input.dataset.text = input.dataset.text ?? input.textContent;
                    input.textContent = input.dataset.text_replace ?? 'Promo copied';
                    setTimeout(function () {
                        btn.textContent = btn.dataset.text ?? '';
                        input.textContent = input.dataset.text ?? '';
                    }, 5000);
                }).catch(function (error) {
                    console.error('Could not copy text: ', error);
                });
            }
            break;
        }

        // tabs
        if (el.matches('[data-tab-btn]')) {
            e.preventDefault();
            if (el.classList.contains('active')) {
                return;
            }

            const btns = el.closest('[data-tab-btn-list]').querySelectorAll('[data-tab-btn]');
            let index;
            for (let i = 0; i < btns.length; i++) {
                btns[i].classList.remove('active');
                if (el === btns[i]) {
                    index = i;
                }
            }
            el.classList.add('active');

            const items = el.closest('[data-tab-wrap]').querySelectorAll('[data-tab-item]');
            items.forEach(item => item.classList.remove('active'));
            items[index].classList.add('active');
        }

        // select
        if (el.matches('[data-select-btn]')) {
            const select = el.closest('[data-select]');
            if (select) {
                select.classList.toggle('active');
            }
        }

        // input text show/hidden
        if (el.matches('[data-eye]')) {
            let inputWrapper = el.parentNode;
            let input = inputWrapper.querySelector('input');
            if (input.value) {
                inputWrapper.classList.toggle('active');
                if (inputWrapper.classList.contains('active')) {
                    inputWrapper.querySelector('input').type = 'text';
                } else {
                    inputWrapper.querySelector('input').type = 'password';
                }
            }
        }

        // show more progress tab list 
        let progressTabList = document.querySelector('.sj-tab-list-progress__list');
        if (progressTabList !== null) {
            if (el.matches('[data-load-more]')) {
                progressTabList.style.height = 'auto';
                progressTabList.style.overflow = 'visible';
                el.classList.add('hide');
            }
        }
        
        //-/-

        // .show-all-providers
        if (
            el.matches('.show-all-providers')
        ) {
            e.preventDefault();
            var softwaresContainer = el.parentNode.parentNode;

            if (softwaresContainer.querySelector('.not-loaded')) {
                fetch('/index.php?route=content/casino/loadsoftwares', {
                    credentials: 'include',
                    cache: 'no-cache',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'xmlhttprequest' }
                })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (json) {
                        softwaresContainer.querySelector('.items').innerHTML = json.item_list;
                        softwaresContainer.querySelector('.items').classList.remove('not-loaded');
                        softwaresContainer.classList.toggle('open');
                    })
                    .catch(function (error) {
                        console.error(error);
                    });
            } else {
                softwaresContainer.classList.toggle('open');
            }

            return;
        }

        // вызов попапа с выигрышем
        if (el.matches('[data-action="showMore"]')) {
            let li = el.closest('li');
            let footer = document.querySelector('.sj-footer');
            let popupWinning = document.getElementById('popup-winning');
            let sjPopup;

            if (!popupWinning) {
                sjPopup = document.createElement('div');
                sjPopup.className = 'sj-popup';
                sjPopup.setAttribute('data-popup-wrap', '');

                popupWinning = document.createElement('div');
                popupWinning.className = 'popup-winning';
                popupWinning.id = 'popup-winning';

                sjPopup.appendChild(document.createElement('div')).className = 'sj-overlay';
                sjPopup.appendChild(popupWinning);

                if (footer && footer.parentNode) {
                    footer.parentNode.insertBefore(sjPopup, footer.nextSibling);
                }
            } else {
                popupWinning.innerHTML = '';
                sjPopup = popupWinning.closest('.sj-popup');
            }

            Array.from(li.children).forEach(function (child) {
                let clonedChild = child.cloneNode(true);
                let showMoreButton = clonedChild.querySelector('.sj-btn');
                if (showMoreButton) showMoreButton.remove();
                popupWinning.appendChild(clonedChild);
            });

            let button_popup = document.createElement('button');
            button_popup.className = 'sj-popup__close';
            button_popup.setAttribute('data-popup-close', '');
            button_popup.setAttribute('type', 'button');
            popupWinning.insertBefore(button_popup, popupWinning.firstChild);

            let items = popupWinning.querySelectorAll('[data-action="showMore"]');
            if (items) {
                items.forEach(function (item) {
                    item.removeAttribute('data-action');
                });
            }

            if (sjPopup) {
                sjPopup.classList.add('active');
            }

            return;
        }

        // shop 
        if (el.matches('[data-add_to_cart]')) {
            let pItem = el.closest('.sj-product__item');
            let pId = pItem.dataset.pid;
            let pMsg = translates.text_item_cart_added;
            let pPrice = el.parentNode.querySelector('.sj-product__price b').innerText;

            cart.update(pId, 1, pPrice);

            show_popup(pMsg, site_data.cart_link, translates.button_go_to_cart, '/site/view/theme/brandnew/img/svg_icons/icon-in-cart.svg#icon-in-cart');
        }
        
        // cart
        var cartForm = document.querySelector('.sj-cart__form');

        if (el.matches('[data-cart_btn]')) {
            
            var cartItem = el.closest('.sj-cart__item');
            var cartItemPID = cartItem.dataset.pid;
            var cartItemAmountText = cartItem.querySelector('.sj-cart__amount .sj-cart__value b');
            var cartItemAmountInput = cartItem.querySelector('.sj-cart__amount input');
            var cartItemPrice = cartItem.querySelector('.sj-cart__price input').value;
            //var cartItemCount = cartItem.parentNode.children.length;

            if (el.matches('[data-btn_remove]')) {
                cart.remove(cartItemPID);
                cartItem.remove();
            } else if (el.matches('[data-btn_plus]')) {
                cart.update(cartItemPID, 1, cartItemPrice);
                cartItemAmountInput.value++;
                cartItemAmountText.innerText = cartItemAmountInput.value;
            } else if (el.matches('[data-btn_minus]')) {
                if (cartItemAmountInput.value - 1 < 1) {
                    cart.remove(cartItemPID);
                    cartItem.remove();
                } else {
                    cart.update(cartItemPID, -1, cartItemPrice);
                    cartItemAmountInput.value--;
                    cartItemAmountText.innerText = cartItemAmountInput.value;
                }
            }

            let totals = cart.get_total();

            let totalItemsCount = cartForm.querySelector('.sj-cart__total_item--count b');
            let totalItemsPrice = cartForm.querySelector('.sj-cart__total_item--price b');

            let accInfoTotalItems = document.getElementById('accInfoTotalItems');
            let accInfoTotalPrice = document.getElementById('accInfoTotalPrice');

            totalItemsCount.innerText = totals['total_items'];
            totalItemsPrice.innerText = totals['total_price'];

            accInfoTotalItems.innerText = totals['total_items'];
            accInfoTotalPrice.innerText = totals['total_price'];
        
            if (totals['total_items'] == 0) {
                cartForm.remove();
            }
        }

        // iframe fullscreen
        if (el.matches('[data-iframe_fullscreen]')) {
            var iframe = document.querySelector(el.dataset.iframe_fullscreen);
            if (!document.fullscreenElement) {
                iframe.requestFullscreen().catch((err) => {
                    console.error(`Error attempting to enable fullscreen mode: ${err.message} (${err.name})`,);
                });
            } else {
              document.exitFullscreen();
            }
            return;
        }

        // anchor
        if (el.matches('a[href*="#"]')) {
            var hash = el.hash;
            var slide_to = document.querySelector(hash);
            var slide_offset = isMobile() ? 70 : 0;
            if (slide_to) {
                e.preventDefault();
                scrollTop(window, offset(slide_to).top - slide_offset);

                if (hash.substr(0, 1) == '#') {
                    history.pushState(null, null, location.origin + location.pathname + hash);
                }
            }
            return;
        }
    }
});

// helper
function offset(el) {
    box = el.getBoundingClientRect();
    docElem = document.documentElement;
    return {
        top: box.top + window.scrollY - docElem.clientTop,
        left: box.left + window.scrollX - docElem.clientLeft
    };
}
function scrollTop(el, value) {
    if (value === undefined) {
        return el.pageYOffset;
    } else {
        if (el === window || el.nodeType === 9) {
            el.scrollTo(el.pageXOffset, value);
        } else {
            el.pageYOffset = value;
        }
    }
}
function scrollLeft(el, value) {
    if (value === undefined) {
        return el.pageXOffset;
    } else {
        if (el === window || el.nodeType === 9) {
            el.scrollTo(value, el.pageYOffset);
        } else {
            el.pageXOffset = value;
        }
    }
}
function isMobile() {
    let item = document.querySelector('.sj-head__toggle');
    return item && window.getComputedStyle(item).display !== 'none';
}