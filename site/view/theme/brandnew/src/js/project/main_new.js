// tables fixes
var body = document.querySelector('body');
let sjTextTable = document.querySelectorAll('.sj-text > table');
sjTextTable.forEach((table) => {
	let wrapper = document.createElement('div');
	table.removeAttribute('border');
	let tableTd = table.querySelectorAll('td');
	tableTd.forEach((td) => {
		td.style.border = '0';
	});

	wrapper.className = 'sj-table-wrap';
	table.parentNode.insertBefore(wrapper, table);
	wrapper.appendChild(table);
});

let sjCutTableWrapper = document.querySelectorAll('.sj-text .cut-table-wrap, .sj-text .sj-table-wrap__cut');

sjCutTableWrapper.forEach((tableWrapper) => {
	let tableCut = tableWrapper.querySelector('.cut-table, .sj-table-wrap');
	let tableLoadMore = tableWrapper.querySelector('.load-more-btn, .sj-table-wrap__cut_buttons');
	tableLoadMore.addEventListener('click', (e) => {
		if (tableCut.offsetHeight == '550') {
			tableCut.style.maxHeight = '100%';
			tableLoadMore.classList.add('active');
		} else {
			tableCut.style.maxHeight = '550px';
			tableLoadMore.classList.remove('active');
		}
		let btnSpan = tableLoadMore.querySelectorAll('span');
		btnSpan.forEach((span) => {
			if (span.style.display == 'block') {
				span.style.display = 'none';
			} else {
				span.style.display = 'block';
			}
		});
	});
});

// Age verification for UK before game

let openAgeForm = document.getElementById('openAgeForm');
let agevStep1 = document.getElementById('agevStep1');
let agevStep2 = document.getElementById('agevStep2');
let closeStep2 = document.getElementById('closeStep2');

if(openAgeForm && agevStep1 && agevStep2 && closeStep2) {
	openAgeForm.addEventListener('click', function() {
		agevStep1.style.display = 'none';
		agevStep2.style.display = '';
		closeStep2.style.display = '';
	});

	closeStep2.addEventListener('click', function() {
		agevStep2.style.display = 'none';
		agevStep1.style.display = '';
		this.style.display = 'none';
	});
}


// moving element from sidebar to main content on mobile
function moveSidebarContent() {
	let sidebarItem = document.querySelector('[data-sidebar-item]');
	let aside = document.querySelector('[data-sidebar]');
	let sidebarSection = document.querySelector('[data-sidebar-section]');

	if (!sidebarItem || !aside || !sidebarSection) return;

	let screenWidth = window.innerWidth;

	if (screenWidth > 1024) {
		if (!aside.contains(sidebarItem)) {
			aside.prepend(sidebarItem);
		}
	} else {
		if (!sidebarSection.contains(sidebarItem)) {
			sidebarSection.appendChild(sidebarItem);
		}
	}
}
moveSidebarContent();

window.addEventListener('resize', moveSidebarContent);


// sticky element
let stickyElement = document.querySelector('[data-sticky-target]');

if (stickyElement) {
	let originalPosition = stickyElement.getBoundingClientRect().top + window.scrollY - 24;

	window.addEventListener('scroll', function() {
		if (window.scrollY >= originalPosition) {
			stickyElement.classList.add('sticky');
		} else {
			stickyElement.classList.remove('sticky');
		}
	});
}

// sticky tabs
let topTabs = document.querySelectorAll('.sj-slot-pc__list');
let topTabsLinks = document.querySelectorAll('.sj-slot-pc__item');

if (topTabs !== null) {
	topTabs.forEach((tabs) => {
		let topTabsItem = tabs.querySelectorAll('a');

		for (const listItem of topTabsItem) {
			listItem.addEventListener('click', (e) => {
				if (!e.target.hasAttribute('target')) {
					e.preventDefault();
					e.stopPropagation();
					const elHref = new URL(
						e.target.getAttribute('href')
					);
					let targetBlock = document.querySelector(elHref.hash);
					if (targetBlock) {
						for (const listItemLinks of topTabsLinks) {
							listItemLinks.classList.remove('active');
							let itemHref = listItemLinks.getAttribute('href');
							if (itemHref.includes(elHref.hash)) {
								listItemLinks.classList.add('active');
							}
						}
						const yOffset = -100; 
						let y = targetBlock.getBoundingClientRect().top + window.scrollY + yOffset;
						window.scrollTo({top: y, behavior: 'smooth'});
					} else {
						window.location.hash = '';
					}
					return false;
				}
			});
		}
	});
}

// youtube
let iframePlay = document.querySelectorAll('.sj-iframe__preview');
if (iframePlay !== null) {
	iframePlay.forEach(function (play) {
		play.addEventListener('click', (e) => {
			let iframe = play.parentNode.querySelector('iframe');
			let iframeSrc = iframe.dataset.src;
			iframe.setAttribute('src', iframeSrc);
		});
	});
}

//cart 

const cart = {
	get_cart() {
		let sj_cart = document.cookie.match(/sj_cart=(\S+)(; |)/);
		sj_cart = sj_cart ? JSON.parse(atob(sj_cart[1].replace(';', ''))) : {};

		return sj_cart;
	},
	write_cart(cart) {
		document.cookie = "sj_cart=" + btoa(JSON.stringify(cart)) + ";path=/;expires=" + new Date(new Date() * 2).toGMTString();
	},
	update(product_id, quantity, price) {
		let sj_cart = cart.get_cart();
		quantity = quantity ? parseInt(quantity) : 1;
		price = price ? parseInt(price) : 0;

		if (!isNaN(quantity) && quantity && !isNaN(price) && price && product_id) {
			if (sj_cart[product_id]) {
				sj_cart[product_id].amount += quantity;
			} else {
				sj_cart[product_id] = {
					'amount': 1,
					'price': price
				};
			}

			cart.write_cart(sj_cart);

			if (sj_cart[product_id].amount < 1) {
				cart.remove(product_id);
			}
		}
	},
	remove(product_id) {
		let sj_cart = cart.get_cart();
		delete sj_cart[product_id];
		cart.write_cart(sj_cart);
	},
	get_total() {
		let sj_cart = cart.get_cart();

		let totals = {
			'total_price': 0,
			'total_items': 0
		};
		for (let pid in sj_cart) {
			totals['total_price'] += sj_cart[pid].price * sj_cart[pid].amount;
			totals['total_items'] += sj_cart[pid].amount;
		}
		return totals;
	}
}

if ('CKEDITOR' in window) {
	CKEDITOR.replace('editor');
}
	
// *
// fetch functions to replace jQuery AJAX
// *
const main = document.querySelector('main');
const html = document.querySelector('html');
const sjForm = document.querySelector('.sj-form');
const sjFormFilter = main.querySelector('.sj-form');
const query = {
	async request(action, formData, formMethod) {
		try {
			let params;
			params = {
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
					//"Content-type": "application/x-www-form-urlencoded; charset=UTF-8"
					//'Content-Type': 'application/json;charset=utf-8'
				},
				method: formMethod,
				cache: "no-cache",
			}
			if (formMethod == 'POST') {
				params.body = formData;
			}
			const response = await fetch(action, params);
			const data = await response.json();
			return data;
		} catch (error) {
			//console.error("Error:", error);
		}
	}
}
const submit = {
	form(targetForm) {
		let c_form = targetForm;
		if (c_form.classList.contains('no-ajax')) {
			return;
		}
	
		let c_action = c_form.getAttribute('action');

		let c_form_ctx = '.' + c_form[0].className.replace(/ /g, '.');
		let c_form_ofs = !isNaN(c_form.getAttribute('ofs')) ? c_form.getAttribute('ofs') : 10;
		let c_form_ntf = c_form.querySelector('.sj_notification');

		let c_form_no_reset = c_form.getAttribute('no-reset') ? 1 : 0;
	
		let c_form_files = c_form.querySelector('.form-image');
		let c_files_alw = true;

		let snd_data = new FormData();

		if (c_files_alw && c_action && !c_form.parentNode.classList.contains('process')) {
			let c_fields = new FormData(c_form);
			for (let [key, value] of c_fields) {
				snd_data.append(key, value);
			}
			c_form.parentNode.classList.add('process');

			result = query.request(c_action, snd_data, 'POST');

			result.then(function(json) {
				
				c_form.parentNode.classList.remove('process');
				
				if (json['redirect']) {
					location.href = json['redirect'];

				// in case of success
				} else if (json['success']) {
					let successEvent = new CustomEvent("success", {
						"detail": json
					});
					c_form.reset();
					if(!c_form.classList.contains('s-reviews')){
						let success_evt = c_form.dispatchEvent(successEvent);
						if (!success_evt || c_form.classList.contains('register')) {

							if(c_form_ntf.innerHTML || c_form_ntf.innerText){
								c_form_ntf.innerHTML = '';
							}

							show_notification({
								'msg': json['success'],
								'ctx': c_form_ctx
							});
						}
					}

					if (c_form_no_reset !== 0) {
						if (c_form_files !== 'null') {
							c_form.querySelectorAll('input[type="file"]').value = '';
							c_form.querySelectorAll('.form-image').remove();
						}

						c_form.trigger('reset');
					}
				} else if (json['error']) {
					if (json['error']['error'] && json['error']['error'] instanceof Array) {
						for (let i = 0; i <= json['error']['error'].length - 1; i++) {
							show_notification({
								'msg': json['error']['error'][i],
								'type': 'error',
								'ctx': c_form_ctx
							});
						}
					}
					let errorMsgElement = c_form.querySelector('.sj-field__error_message');
					let firstInputField = c_form.querySelector('.sj-field:first-child');
					

					for (let error in json['error']) {
						if (json['error'].hasOwnProperty(error) && error != 'error') {
							let errorAlert = json['error'][error];
							if(errorAlert.indexOf('long-alert') > 0 ){
								console.log(errorAlert);
							} else {
								firstInputField.classList.add('sj-field__error');
								errorMsgElement.classList.remove('hide');
								errorMsgElement.innerHTML = errorAlert;
							}
						}
					}
				}
				
                let captcha = form.querySelector('.h-captcha');
				if (captcha !== null){
					let wgt_id = captcha.dataset.hcaptchaId ? captcha.dataset.hcaptchaId : 0;
					hcaptcha.reset(wgt_id);
				}
			});
		}
		return false;
	}
}

const showProvidersBtn = document.querySelector('#showProviders');
if (showProvidersBtn !== null) {
	showProvidersBtn.addEventListener('click', (e) => {
		let params = {
				headers: {
						'Accept': 'text/html',
						'X-Requested-With': 'XMLHttpRequest',
				},
				method: 'GET',
				credentials: 'include',
				cache: "no-cache"
		};

		fetch(e.target.dataset.action, params)
		.then(function (r) {
				return r.json();
		})
			.then(function (data) {
				if (data.html) {
					main.insertAdjacentHTML(
						'beforeend', 
						data.html
					);

					let content_modal = document.querySelector('.popup-provider');
					let list = content_modal.querySelector('.popup-provider__list_wrap');
					let items = list.querySelectorAll('.popup-provider__item');;
					let input = content_modal.querySelector('input');
					let total = 0;
					input.addEventListener('keyup', (e) => {
						let value = e.target.value.toLowerCase();
						items.forEach(function (item) {
							total = 0;
							if (value.length == 0 || item.innerText.toLowerCase().indexOf(value) > -1) {
								item.classList.remove('hide');
								total++;
							} else {
								item.classList.add('hide');
							}
						});
					});
					input.addEventListener('keydown', (e) => {
						if (e.key == 13 && total == 1) {
							e.preventDefault();
							location.href = list.querySelector('a:not(.hide)').getAttribute('href');
							
							return false;
						}
					});
				}
		})
		.catch(function (error) {
				console.error(error);
		});
	});
}


// comments
// const commentForm = document.querySelector('.sj-add-comment__form');
// const commentList = document.querySelector('.sj-comment__list');

// if (commentForm !== null) {
//   let commentFormSubmit = commentForm.querySelector('.sj-field__submit button');
//   let commentErrorClass = '.sj-field__error';
//   commentFormSubmit.addEventListener('click', (e) => {
//     submit.form(e);
//   });
//   commentForm.addEventListener("success", function(e) {
//     let html = e.detail.review_html;
//     if (html !== 'undefined') {
//       commentList.insertAdjacentHTML(
//         'beforeend', 
//         html
//       );
//     }
//   });
// }
