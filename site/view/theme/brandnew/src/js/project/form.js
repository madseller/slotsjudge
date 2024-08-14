// hcaptcha
function HcapthaLoad() {
    if ('hcaptcha' in window) {
        return;
    }
    let script = document.createElement('script');
    script.async = true;
    script.src = 'https://js.hcaptcha.com/1/api.js';
    document.body.append(script);
}

function HcapthaInit(captcha) {
    if ('hcaptcha' in window) {
        captcha.innerHTML = '';
        let hcaptcha_id = hcaptcha.render(captcha, { sitekey: captcha.dataset.sitekey });
        captcha.dataset.hcaptchaId = hcaptcha_id;   
    } else {
        HcapthaLoad();
    }
}

// если html код капчи уже разместился
if ("IntersectionObserver" in window) {
    // Video
    var hcaptchaObserver = new IntersectionObserver(function (entries, observer) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                HcapthaLoad();
                hcaptchaObserver.unobserve(entry.target);
            }
        });
    });
    var items = document.querySelectorAll('.h-captcha[data-sitekey]');
    items.forEach(function (el) {
        hcaptchaObserver.observe(el);
    });
}

// если html код капчи разместился потом
var target = document.documentElement || document.body;
const config = {
    childList: true,
    subtree: true
};
const callback = function (mutations, observer) {
    mutations.forEach(function (mutation) {
        if (mutation.type === 'childList') {
            mutation.addedNodes.forEach(function (node) {
                if (!(node instanceof HTMLElement)) return;

                if (node.matches('.h-captcha[data-sitekey]:not([data-hcaptcha-id])')) {
                    HcapthaInit(node);
                }
                var nodes = node.querySelectorAll('.h-captcha[data-sitekey]:not([data-hcaptcha-id])');
                if (nodes) {
                    nodes.forEach(function (el) {
                        HcapthaInit(el);
                    });
                }
            });
        }
    });
};
const observer = new MutationObserver(callback);
observer.observe(target, config);
// end hcaptcha

function remove_error(block) {
    var errors = block.querySelectorAll('.sj-field__error_message, .sj-field__help_error_message, .sj-tab-list-settings__error_message');
    errors.forEach(function (item) {
        item.remove();
    });
    block.classList.remove('sj-field__error', 'sj-tab-list-settings__input_error');
    var errors = block.querySelectorAll('.sj-field__error, .sj-tab-list-settings__input_error');
    errors.forEach(function (item) {
        item.classList.remove('sj-field__error', 'sj-tab-list-settings__input_error');
    });
    var errors = block.querySelectorAll('.sj-field__help_error');
    errors.forEach(function (item) {
        item.classList.remove('sj-field__help_error');
    });
}

// form submit
document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('[data-form]')) {
        return;
    }
    e.preventDefault();
    if (form.classList.contains('process') || !form.action) {
        return;
    }

    form.classList.add('process');

    var url = new URL(form.action);
    var data = new FormData(form);
    let params = {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            //"Content-type": "application/x-www-form-urlencoded; charset=UTF-8"
            //'Content-Type': 'application/json;charset=utf-8'
        },
        method: form.method,
        credentials: 'include',
        cache: "no-cache",
    };

    if (e.submitter && e.submitter.name && e.submitter.value) {
        data.append(e.submitter.name, e.submitter.value);
    }
    var btn_page = document.querySelector('[form="' + form.id + '"]');
    if (btn_page) {
        btn_page.disabled = true;
        btn_page.classList.add('process');
    }

    if (form.method.toUpperCase() == 'POST') {
        var input_files = form.querySelectorAll('[type="file"]');

        if (input_files.length) {
            var json = {
                error: {}
            };

            input_files.forEach(function (input) {
                let files = input.files;
                let input_files_error = [];

                if (files.length > 0) {
                    for (var i = 0, file; file = files[i]; i++) {
                        if (site_data.allow_types.indexOf(file.type) == -1 || file.type == '') {
                            input_files_error.push(translates.text_file + ' ' + file.name + ' - ' + translates.error_upload_8);
                        } else if (file.size > site_data.allow_max_size) {
                            input_files_error.push(translates.text_file + ' ' + file.name + ' - ' + translates.error_upload_9);
                        }

                        if (input_files_error.length == 0) {
                            data.append(input.name + '[' + i + ']', file);
                        }
                    }
                }

                if (input_files_error.length > 0) {
                    json.error[input.name] = input_files_error.join('<br>');
                }
            });

            if (json.error.length > 0) {
                let event = new CustomEvent('error', { detail: json, bubbles: true });
                form.dispatchEvent(event);

                return;
            }
        }

        params.body = data;
    } else {
        if (url.search) {
            url.search += '&' + new URLSearchParams(data).toString();
        } else {
            url.search = new URLSearchParams(data).toString();
        }
    }

    fetch(url, params)
        .then(function (r) {
            if (r.status == 403) {
                loadLogin();
            }
            if (!r.ok) {
                throw new Error("Network response was not OK");
            }
            return r.json();
        })
        .then(function (json) {
            if (form.method.toUpperCase() == 'POST') {
                var successes = form.querySelectorAll('.notification .success');
                successes.forEach(function (item) {
                    item.remove();
                });
                remove_error(form);
                form.classList.remove('process');

                let captcha = form.querySelector('.h-captcha');
				if (captcha !== null){
					let wgt_id = captcha.dataset.hcaptchaId ? captcha.dataset.hcaptchaId : 0;
					hcaptcha.reset(wgt_id);
				}

                if (json['redirect']) {
                    location.href = json['redirect'];
                } else if (json['success']) {
                    let event = new CustomEvent('success', { detail: json, bubbles: true });
                    form.dispatchEvent(event);

                    // if (!c_form_no_reset) {
                    //     if (rq_files.length) {
                    //         let items = c.querySelectorAll('input[type="file"]');
                    //         items.forEach(function (item) {
                    //             item.value = '';
                    //             item.change();
                    //         });
                    //     }

                    //     form.reset();
                    // }
                } else if (json['error']) {
                    let event = new CustomEvent('error', { detail: json, bubbles: true });
                    form.dispatchEvent(event);
                }
            } else {
                let event = new CustomEvent('success', { detail: json, bubbles: true });
                form.dispatchEvent(event);
            }
        })
        .catch(function (error) {
            form.classList.remove('process');
            show_information(translates.error_send_system);
            console.error(error);
        });
});

// form reset
document.addEventListener('reset', function (e) {
    var form = e.target;

    // clear images
    var items = form.querySelectorAll('[data-field_file="image_block"]');
    if (items) {
        items.forEach(function (image_block) {
            let div_image = image_block.querySelector('.sj-field__image');
            if (div_image) {
                div_image.remove();
            }
        });
    }

    // select default
    var items = form.querySelectorAll('[data-select]');
    if (items) {
        items.forEach(function (select) {
            let inputs = select.querySelectorAll('input');
            inputs.forEach(input => {
                input.checked = input.hasAttribute('checked');
            });
            let input = select.querySelector('input:checked');
            let content = select.querySelector('[data-select-content]');
            if (content) {
                content.innerText = input ? input.parentNode.innerText : content.dataset.selectContent;
            }
        });
    }
});

// form success
document.addEventListener('success', function (e) {
    var form = e.target;
    var detail = e.detail;

    // Filters
    if (
        form.matches('[data-form="slot__filter_list"]')
        || form.matches('[data-form="filter__list"]')
        || form.matches('[data-form="provider__search"]')
    ) {
        var list = form.matches('[data-form="filter__list"]') ? form.parentNode.nextElementSibling : form.nextElementSibling;
        let div = document.createElement('div');
        div.innerHTML = detail['result_html'];
        var btn_page = document.querySelector('[form="' + form.id + '"]');
        if (!detail['page'] || detail['page'] == 1) {
            list.innerHTML = '';
            if (btn_page && detail['show_more']) {
                btn_page.value = 2;
                btn_page.classList.remove('hide');
            }
        }
        if (btn_page && !detail['show_more']) {
            btn_page.classList.add('hide');
        } else if (btn_page && detail['page'] > 1) {
            btn_page.value = parseInt(detail['page']) + 1;
        }
        if (btn_page) {
            btn_page.disabled = false;
            btn_page.classList.remove('process');
        }
        list.append(...div.firstElementChild.children);
    }

    // Filter bonus
    if (
        form.matches('[data-form="cbonus__list"')
    ) {
        var list = form.nextElementSibling;
        list.innerHTML = detail['item_list'];
    }

    // Forms default
    if (
        form.matches('[data-form="comment__form"]')
        || form.matches('[data-form="contact__form"]')
        || form.matches('[data-form="forgotten__form"')
        || form.matches('[data-form="post__form"]')
        || form.matches('[data-form="verify_age"]')
        || form.matches('[data-form="account"]')
    ) {
        var btn_page = document.querySelector('[form="' + form.id + '"]');
        if (btn_page) {
            btn_page.disabled = false;
            btn_page.classList.remove('process');
        }
        show_popup(detail.success, detail.success_link ?? '');

        var inputs = form.querySelectorAll('input[name],select[name],textarea[name]');
        inputs.forEach(function (input) {
            if (input.type == 'checkbox' || input.type == 'radio') {
                if (input.checked) {
                    input.setAttribute('checked', '');
                } else {
                    input.removeAttribute('checked');
                }
            } else {
                input.setAttribute('value', input.value);
            }
        });
        form.reset();
    }

    // Cart checkout
    if (form.matches('[data-form="checkout"]')) {
        form.classList.remove('process');
        form.reset();
        cart.write_cart({});
        form.remove();
        show_popup(detail.success);
    }
});

// form error
document.addEventListener('error', function (e) {
    var form = e.target;
    var detail = e.detail;
    var offset = form.dataset.offset ?? 10;

    for (let error in detail['error']) {
        if (!detail['error'].hasOwnProperty(error)) {
            continue;
        }
        if ((error == 'error' || error == 'warning')) {
            if (detail['error'][error] instanceof Array) {
                for (var i = 0; i < detail['error'][error].length; i++) {
                    show_information(detail['error'][error][i]);
                }
            } else {
                show_information(detail['error'][error]);
            }

            // scrollTop(window, offset(c_form_ntf).top - offset); some kind of old shit :)?
        } else {
            var item = form.querySelector('input[name="' + error + '"], select[name="' + error + '"], textarea[name="' + error + '"]');

            if (item) {
                let class_name__error_message = form.dataset.form == 'account' ? 'sj-tab-list-settings__error_message' : 'sj-field__error_message';
                if (item && item.closest('[data-select]')) {
                    item.closest('[data-select]').insertAdjacentHTML('afterend', '<div class="' + class_name__error_message + '">' + detail['error'][error] + '</div>');
                } else if (item.closest('[data-rating]')) {
                    item.closest('[data-rating]').insertAdjacentHTML('beforebegin', '<div class="' + class_name__error_message + '">' + detail['error'][error] + '</div>');
                } else if (item.parentNode.tagName == 'TEXTAREA') {
                    item.insertAdjacentHTML('afterend', '<div class="' + class_name__error_message + '">' + detail['error'][error] + '</div>');
                } else if (item.type == 'file') {
                    item.insertAdjacentHTML('beforebegin', '<span class="sj-field__help_error_message">' + Object.values(detail['error'][error]).join('br') + '</span>');
                    item.parentNode.classList.add('sj-field__help_error');
                } else if (item.type == 'password' || item.type == 'text' || item.type == 'email') {
                    item.insertAdjacentHTML('afterend', '<div class="' + class_name__error_message + '">' + detail['error'][error] + '</div>');
                } else if (item.type == 'checkbox') {
                    item.parentNode.insertAdjacentHTML('beforeend', '<div class="' + class_name__error_message + '">' + detail['error'][error] + '</div>');
                } else {
                    item.insertAdjacentHTML('afterend', '<div class="' + class_name__error_message + '">' + detail['error'][error] + '</div>');
                }
                if (form.dataset.form == 'verify_age') {
                    item.closest('.agev__form_field_value').classList.add('sj-field__error');
                } else if (form.dataset.form == 'account') {
                    if (item.type == 'file') {
                        item.closest('.sj-tab-list-settings__block').classList.add('sj-tab-list-settings__input_error');
                    } else {
                        item.closest('.sj-tab-list-settings__input').classList.add('sj-tab-list-settings__input_error');
                    }
                } else {
                    item.closest('.sj-field').classList.add('sj-field__error');
                }
            }
        }
        if (error == 'captcha') {
            let captcha = document.querySelector('.h-captcha');
            captcha.insertAdjacentHTML('afterend', '<div class="sj-field__error_message">' + detail['error'][error] + '</div>');
        }
    }

});

var timeout_input;
document.addEventListener('input', function (e) {
    let input = e.target;
    if (input.matches('[name="search"]')) {
        clearTimeout(timeout_input);
        timeout_input = setTimeout(() => {
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }, 500);
    }

    if (input.tagName == 'TEXTAREA') {
        let el = input.parentNode.querySelector('[data-length] span');
        if (el) {
            var length = input.value.length;
            if (length > 500) {
                input.value = input.value.slice(0, 500);
                length = 500;
            }
            el.innerText = 500 - length;
        }
    }
});

// input change
document.addEventListener('change', function (e) {
    let input = e.target;
    // file
    if (
        input.matches('[type="file"]')
        && input.closest('[data-field_file="wrap"]')
    ) {
        var form = input.closest('form');
        let field = input.closest('[data-field_file="wrap"]');
        var image_block = field.querySelector('[data-field_file="image_block"]');
        var placeholder = field.querySelector('.sj-tab-list-settings__avatar'); // TODO: refactoring post_processor
        let button_add = field.querySelector('[data-field_file="add"]');
        let button_delete = field.querySelector('[data-field_file="delete"]');

        var errors = field.querySelectorAll('.sj-field__error_message, .sj-tab-list-settings__error_message');
        errors.forEach(function (item) {
            item.remove();
        });

        var files = input.files;
        var multiple = input.multiple;
        var max = parseInt(input.dataset.max ?? 0);

        if (files.length && !multiple) {
            if (image_block) {
                let div_image = image_block.querySelector('.sj-field__image');
                if (div_image) {
                    div_image.remove();
                }
            }
            if (placeholder) {
                placeholder.src = input.dataset.placeholder;
            }
        }
        var error = false;

        for (var i = 0, file; file = files[i]; i++) {
            if (site_data.allow_types.indexOf(file.type) < 0) {
                error = translates.error_upload_8;
            }
            if (!error && file.size > site_data.allow_max_size) {
                error = translates.error_upload_9;
            }

            if (error) {
                let class_name__error_message = form.dataset.form == 'account' ? 'sj-tab-list-settings__error_message' : 'sj-field__error_message';
                input.value = '';
                input.insertAdjacentHTML('beforebegin', '<div class="' + class_name__error_message + '">' + error + '</div>');
                button_delete.classList.add('hide');
            } else {
                var reader = new FileReader();
                reader.addEventListener(
                    "load",
                    () => {
                        if (image_block) {
                            image_block.insertAdjacentHTML('afterbegin',
                                `<div class="sj-field__image">
                                    <img src="${reader.result}" width="120" height="120">
                                    <span class="sj-field__image_close" data-field_file="delete"></span>
                                </div>`
                            );
                        }
                        if (placeholder) {
                            placeholder.src = reader.result;
                        }

                        button_delete.classList.remove('hide');
                    },
                    false,
                );

                reader.readAsDataURL(file);
            }
        }
        button_add.disabled = false;
    }

    // rating
    if (input.closest('[data-rating]')) {
        var el = input.closest('[data-rating]').nextElementSibling;
        if (el) {
            el.innerText = input.value;
        }
        return;
    }

    // select
    if (input.closest('[data-select]')) {
        let select = input.closest('[data-select]');
        let content = select.querySelector('[data-select-content]');
        content.innerText = input.parentNode.innerText;
        select.classList.remove('active');
    }

    if (input.matches('[data-check]')) {
        e.preventDefault();

        let selector = input.dataset.check;
        if (selector) {
            let targets = document.querySelectorAll(selector);
            targets.forEach(function (target) {
                target.classList.toggle('active', input.checked);
            });
        }
        return;
    }

    // Slots
    var form = input.closest('form');
    if (form && (
        form.matches('[data-form="slot__filter_list"]')
        || form.matches('[data-form="filter__list"]')
        || form.matches('[data-form="provider__search"]')
    )) {
        if (input.closest('[data-select]')) {
            var selects = form.querySelectorAll('.sj-filter__item');
            selects.forEach(function (select) {
                if (!select.contains(input)) {
                    let content = select.querySelector('[data-select-content]');
                    if (content) {
                        content.innerText = content.dataset.selectContent;   
                    }
                }
            });
        }
        form.requestSubmit();
    }

    // Bonuses
    if (form && (
        form.matches('[data-form="cbonus__list"]')
    )) {
        const select = input.closest('[data-another_click]');
        if (select) {
            select.classList.add('active');
        }
        form.requestSubmit();
    }
});

document.addEventListener('click', function (e) {
    var el = e.target;

    // remove error from field
    if (
        (
            el.matches('input')
            || el.matches('textarea')
            || el.matches('select')
            || el.closest('[data-select]')
            || el.closest('[data-rating]')
        )
        && (
            el.closest('.sj-field__error')
            || el.closest('.sj-tab-list-settings__input_error')
        )
    ) {
        remove_error(el.closest('.sj-field__error'));
        remove_error(el.closest('.sj-tab-list-settings__input_error'));
    }

    // input file add
    if (
        el.matches('[data-field_file="add"]')
        && el.closest('[data-field_file="wrap"]')
    ) {
        let field = el.closest('[data-field_file="wrap"]');
        let input = field.querySelector('[type="file"]');

        input.click();
        return;
    }
    // input file delete
    if (
        el.matches('[data-field_file="delete"]')
        && el.closest('[data-field_file="wrap"]')
    ) {
        let field = el.closest('[data-field_file="wrap"]');
        let input = field.querySelector('[type="file"]');

        let div_image = field.querySelector('.sj-field__image');
        if (div_image) {
            div_image.remove();
            let button_delete = field.querySelector('[data-field_file="delete"]');
            if (button_delete) {
                el = button_delete;
            }
        }
        let placeholder = field.querySelector('.sj-tab-list-settings__avatar'); // TODO: refactoring post_processor
        if (placeholder) {
            placeholder.src = input.dataset.placeholder;
        }
        input.value = '';
        el.classList.add('hide');
        return;
    }

    // like_dislike send
    if (
        el.matches('[data-like_dislike]')
        || el.closest('[data-like_dislike]')
    ) {
        e.preventDefault();
        let item = el.closest('[data-like_dislike]') ?? el;
        let parent = el.closest('[data-content_type]');
        let action = '/index.php?route=api/ajax_request&t=like_dislike';
        let data = new FormData();
        data.append('like', item.dataset.like_dislike);
        data.append('content_type', parent.dataset.content_type ?? '');
        data.append('content_type_id', parent.dataset.content_type_id ?? 0);

        let params = {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            method: 'POST',
            credentials: 'include',
            cache: "no-cache",
            body: data
        };

        fetch(action, params)
        .then(function (r) {
            if (r.status == '403') {
                loadLogin();
                return [];
            }
            return r.json();
        })
        .then(function (json) {
            if (json['redirect']) {
                location.href = json['redirect'];
            } else if (json['success']) {
                let items = parent.querySelectorAll('[data-like_dislike]');
                items.forEach(function (item) {
                    if (item.dataset.like_dislike == 1) {
                        item.lastElementChild.innerText = json['likes'];
                    } else if (item.dataset.like_dislike == -1) {
                        item.lastElementChild.innerText = json['dislikes'];
                    }
                });
            } else if (json['error']) {
                show_information(json['error']);
            }
        })
        .catch(function (error) {
            console.error(error);
        });
        return;
    }

    // tell us
    if (
        el.matches('[data-casino_slot_id]')
        || el.closest('[data-casino_slot_id]')
    ) {
        e.preventDefault();
        var item = el.closest('[data-casino_slot_id]') ?? el;
        let data = new FormData();
        data.append('type', 'iFrame user-detected error');
        data.append('casino_slot_id', item.dataset.casino_slot_id);

        fetch('/index.php?route=content/casino_slot/add_error_to_list', {
            method: "POST",
            body: data,
            credentials: 'include',
            cache: 'no-cache',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'xmlhttprequest' }
        })
        .then(function (r) {
            return r.json();
        })
        .then(function (json) {
            if (json.error) {
                show_information(json.error);
            }
            if (json.success) {
                show_information(json.success, 'success');
            }
        })
        .catch(function (error) {
            console.error(error);
        });
        return;
    }

    // pop up login
    if (
        el.matches('[data-popup="slot_review"]')
        || el.closest('[data-popup="slot_review"]')
    ) {
        e.preventDefault();
        var popup = document.getElementById('popup_slot_review');
        if (popup) {
            popup.classList.add('active');
        } else {
            loadLogin();
        }
        return;
    }

    // pop up login
    if (
        el.matches('[data-popup="login"]')
        || el.closest('[data-popup="login"]')
    ) {
        e.preventDefault();
        loadLogin();
        return;
    }

    // pop up registration
    if (
        el.matches('[data-popup="registration"]')
        || el.closest('[data-popup="registration"]')
    ) {
        e.preventDefault();
        loadReg();
        return;
    }

    // notification more
    if (el.matches('[data-show_more]')) {
        e.preventDefault();
        let action = el.dataset.show_more;
        el.parentNode.classList.add('process');

        fetch(action, {
            credentials: 'include',
            cache: 'no-cache',
            headers: { 'Accept': 'text/html', 'X-Requested-With': 'xmlhttprequest' }
        })
        .then(function (r) {
            return r.text();
        })
        .then(function (html) {
            let div = document.createElement('div');
            div.innerHTML = html;
            var account_notification_list = document.getElementById('account_notification_list');
            div.querySelectorAll('[data-activity_id]').forEach(function (item) {
                account_notification_list.append(item);
            });
            let show_more = div.querySelector('[data-show_more]');
            if (show_more) {
                el.dataset.show_more = show_more.dataset.show_more;
            } else {
                el.remove();
            }
            el.parentNode.classList.remove('process');
        })
        .catch(function (error) {
            console.error(error);
            el.parentNode.classList.remove('process');
        });

        return;
    }
});

var search_input = document.getElementById('search-input');
var search_list = document.getElementById('search-list');
var timeout_search;

search_input.addEventListener('input', function (e) {
    var input = e.target;

    clearTimeout(timeout_search);
    timeout_search = setTimeout(() => {
        var form = input.closest('form');
        var value = input.value;

        if (value.length > 2 && !form.parentNode.classList.contains('process')) {
            form.parentNode.classList.add('process');

            var url = new URL(form.action);
            var data = new FormData(form);
            let params = {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                method: 'POST',
                credentials: 'include',
                cache: "no-cache",
                body: data
            };

            fetch(url, params)
                .then(function (r) {
                    return r.json();
                })
                .then(function (json) {
                    search_list.innerHTML = json['results_html'];
                    form.parentNode.classList.remove('process');
                    var groups = search_list.querySelectorAll('[data-content_type]');
                    groups.forEach(function (group) {
                        var button_show_more = group.querySelector('[data-button_show_more]'),
                            resultItems = group.querySelectorAll('[data-item]');

                        resultItems.forEach(function (item, i) {
                            if (i > 7) return;
                            item.classList.remove('hide');
                        });

                        if (button_show_more) {
                            button_show_more.addEventListener('click', function (e) {
                                e.stopPropagation();
                                resultItems = [...resultItems].filter(function (item) {
                                    return item.classList.contains('hide');
                                });
                                resultItems.forEach(function (item, i) {
                                    if (i > 7) return;
                                    item.classList.remove('hide');
                                });
                                var resultCount = resultItems.length - 8;

                                if (resultCount > 0) {
                                    button_show_more.querySelector('span').innerText = resultCount;
                                } else {
                                    button_show_more.remove();
                                }
                            });
                        }
                    });
                })
                .catch(function (error) {
                    console.error console.error(error);
                });
        }
    }, 500);
});


// show more 
const showMore = document.querySelector('button.load-more-btn[data-url]');
if (showMore) {
    showMore.addEventListener('click', (e) => {
        e.preventDefault();
        let elParent = e.target.closest('section');
        elParent.classList.add('process');
        let more_elm = document.querySelector(e.target.dataset.elm);
        let url = e.target.dataset.url ?? '';
        let data = new FormData();
        if (parseInt(e.target.dataset.page ?? 0)) {
            data.append('page', e.target.dataset.page);
        }
        if (parseInt(e.target.dataset.field_id ?? 0)) {
            data.append('field_id', e.target.dataset.field_id);
        }
        if (parseInt(e.target.dataset.casino_category_id ?? 0)) {
            data.append('casino_category_id', e.target.dataset.casino_category_id);
        }
        var keys = {
            'start': 'start',
            'limit': 'limit',
            'exclude': 'exclude',
            'not_availible': 'not_availible',
            'field_id': 'field_id',
            'mode': 'mode'
        }
        for (const k in keys) {
            if (keys.hasOwnProperty(k)) {
                const v = keys[k];
                if (e.target.dataset[v] ?? false) {
                    data.append(k, e.target.dataset[v]);
                }
            }
        }

        let params = {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            method: 'POST',
            credentials: 'include',
            cache: "no-cache",
            body: data
        };

        fetch(url, params)
        .then(function (r) {
            return r.json();
        })
        .then(function (json) {
            elParent.classList.remove('process');
            if (json.result_html) {
                more_elm.insertAdjacentHTML(
                    'beforeend',
                    json.result_html
                );
                showMore.dataset.page = parseInt(showMore.dataset.page) + 1;

                if (json.exclude) {
                    showMore.dataset.exclude = json.exclude;
                } else {
                    showMore.dataset.exclude = '';
                }
                
                if (json.not_availible) {
                    showMore.dataset.not_availible = json.not_availible;
                }
                
                if (json.start) {
                    showMore.dataset.start = json.start;
                }
            }
            if (!json.view_show_more) {
                showMore.remove();
            }
        })
        .catch(function (error) {
            console.error(error);
        });
    });
}

function show_information(msg, type = 'error') {
    let html = '';
    switch (type) {
        // https://seobrotherslv.slack.com/archives/C067NCS010A/p1710153036836519
        // case 'success_green':
        //     html = `<div class="sj-notification__success sj-notification__success_green active" data-popup-wrap="">
        //         <div class="sj-notification__icon">
        //             <svg>
        //                 <use xlink:href="/site/view/theme/brandnew/img/svg_icons/icon-check.svg#icon-check"></use>
        //             </svg>
        //         </div>
        //         <p class="sj-notification__message">${msg}</p>
        //         <span class="sj-notification__close" data-popup-close=""></span>
        //     </div>`;
        //     break;
        // case 'error_red':
        //     html = `<div class="sj-notification__error sj-notification__error_red active" data-popup-wrap="">
        //         <div class="sj-notification__icon">
        //             <svg>
        //                 <use xlink:href="/site/view/theme/brandnew/img/svg_icons/icon-alert.svg#icon-alert"></use>
        //             </svg>
        //         </div>
        //         <p class="sj-notification__message">${msg}</p>

        //         <span class="sj-notification__close" data-popup-close=""></span>
        //     </div>`;
        //     break;
        case 'success':
            html = `<div class="sj-notification__success active" data-popup-wrap="">
                <div class="sj-notification__icon">
                    <svg>
                        <use xlink:href="/site/view/theme/brandnew/img/svg_icons/icon-check.svg#icon-check"></use>
                    </svg>
                </div>
                <p class="sj-notification__message">${msg}</p>

                <span class="sj-notification__close" data-popup-close="" data-ok-redirect=""></span>
            </div>`;
            break;
        case 'error':
            html = `<div class="sj-notification__error active" data-popup-wrap="">
                <div class="sj-notification__icon">
                    <svg>
                        <use xlink:href="/site/view/theme/brandnew/img/svg_icons/icon-alert.svg#icon-alert"></use>
                    </svg>
                </div>
                <p class="sj-notification__message">${msg}</p>

                <span class="sj-notification__close" data-popup-close=""></span>
            </div>`;
            break;
    }
    let notice = document.querySelector('.sj-notification');
    if (!notice) {
        notice = document.createElement('div');
        notice.classList.add('sj-notification');
        document.body.append(notice);
    }

    notice.insertAdjacentHTML('beforeend', html);

    let notificationElement = notice.querySelectorAll('[data-popup-wrap]');
    setTimeout(() => {
        for (let t = 0; t < notificationElement.length; t++) {
            if (!notificationElement[t].style.opacity) {
                notificationElement[t].style.opacity = 1;
            }
            let fadeEffect = setInterval(function () {
                if (notificationElement[t].style.opacity > 0) {
                    notificationElement[t].style.opacity -= 0.05;
                } else {
                    clearInterval(fadeEffect);
                    notificationElement[t].classList.remove('active');
                }
            }, 50);
        }
	}, 12000);
}

function show_popup(msg, link = '', button = '', icon = '') {
    let redirectUrl = '';
    if (body != null) {
        var bodyClass = body.className;

        if (bodyClass == 'account-account-cart') {
            redirectUrl = '/';
        }
    }
    
    button = button ? button : translates.button_ok;
    link = link ? `<a href="${link}" class="sj-btn sj-btn__green">${button}</a>`: `<button type="button" class="sj-btn sj-btn__green" data-popup-close="${redirectUrl}">${button}</button>`;
    icon = icon ? `<use xlink:href="${icon}"></use>`: `<use xlink:href="/site/view/theme/brandnew/img/svg_icons/icon-advantage.svg#icon-advantage"></use>`;
    let html = `<div class="sj-popup active" data-popup-wrap="">
        <div class="sj-overlay"></div>
        <div class="popup-post">
            <span class="sj-popup__close" data-popup-close="${redirectUrl}"></span>
            <span class="popup-post__icon">
                <svg>${icon}</svg>
            </span>
            <p>${msg}</p>
            ${link}
        </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
}



// login registration pop up
const popup_account = document.getElementById('popup_account');
const popup_account_content = popup_account.querySelector('.sj-popup-content');

function loadLogin() {
    let action = "/index.php?route=api/ajax_request&t=login";
    if (site_data.current_route == 'content/casino_slot/view') {
        action += "&is_slot_review=1";
    }
	let result = query.request(action, false, 'GET');

	result.then(function(json) {
		if (json !== 'undefined') {
			popup_account_content.innerHTML = json.html;
			popup_account.classList.add("active");
		}
	});
}
function loadReg() {
    let action = "/index.php?route=api/ajax_request&t=register";
    if (site_data.current_route == 'content/casino_slot/view') {
        action += "&is_slot_review=1";
    }
	let result = query.request(action, false, 'GET');

	result.then(function(json) {
		if (json !== 'undefined') {
			popup_account_content.innerHTML = json.html;
			popup_account.classList.add("active");
		}
	});
}

function read_activity(actions) {
    let url = '/index.php?route=api/ajax_request&t=read_activity';
    let data = new FormData();
    actions.forEach(function (item) {
        data.append('activity_ids[]', item.dataset.activity_id);
    });

    let params = {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        method: 'POST',
        credentials: 'include',
        cache: "no-cache",
        body: data
    };

    fetch(url, params)
        .then(function (r) {
        if (r.status == 403) {
            location.reload();
        }
        if (!r.ok) {
            throw new Error("Network response was not OK");
        }
        return r.json();
    })
    .then(function (json) {
        let actions = document.querySelectorAll('[data-activity_id]');
        actions.forEach(function (item) {
            item.removeAttribute('data-activity_id');
        });
    })
    .catch(function (error) {
        console.error(error);
    });   
}
let actions = document.querySelectorAll('#account_notification_list [data-activity_id]');
if (actions.length > 0) {
    read_activity(actions);
}

let header_notification_button = document.getElementById('header_notification_button');
if (header_notification_button) {
    var header_notification_send = false;
    var header_notification_read = function (e) {
        if (header_notification_send) {
            return;
        }
        header_notification_send = true;
        let actions = document.querySelectorAll(header_notification_button.dataset.target + ' [data-activity_id]');
        if (actions.length > 0) {
            read_activity(actions);
        }
    };
    header_notification_button.addEventListener('click', header_notification_read);
    header_notification_button.addEventListener('mouseenter', header_notification_read);
}