import Swiper from 'swiper';
import {Navigation, Pagination, Autoplay} from 'swiper/modules';
import Lazy from 'vanilla-lazyload';
import AirDatepicker from 'air-datepicker';
import * as M from 'materialize-css';
import Hyphenator from './lib/hyphenate';
import { error } from 'jquery';

Swiper.use([Navigation, Pagination, Autoplay]);

interface IDocSuccessResponse{
	id:number,
	pagetitle:string,
	content:string,
	author:string,
	rank:string
}

interface IDocErrorResponse{
	message:string
}

$(() => {

	let nestedSlider:Swiper;
	let questionsSlider:Swiper;
	
	Hyphenator.prototype.hyphenate('p, h1, h2');

	// Предыдущее/следующее видео
	$('body').on('click', '.video-arrow', (e:JQuery.ClickEvent) => {
		e.preventDefault();

		// Общее число видео
		let videoCount = document.querySelectorAll('.video-entry').length;
		let modalEl = $(e.currentTarget).parents('.modal').get(0);
		let current = parseInt(modalEl.dataset['index']);
		let direction = $(e.currentTarget).hasClass('.bx.bx-chevron-right') ? 1 : -1;
		let nextIndex = current + direction;

		if(nextIndex > videoCount){
			nextIndex = 1
		}

		if(nextIndex == 0){
			nextIndex = videoCount;
		}

		let nextEntry = <HTMLElement>document.querySelector('.video-entry[data-index="' + nextIndex + '"]');
		let linkEl = <HTMLElement>nextEntry.querySelector('.video-trigger:last-of-type')
		let link = linkEl.dataset['link'] + "&autoplay=1";
		let nameEl = <HTMLElement>nextEntry.querySelector('.title a');
		let name = nameEl.textContent;
		let videoEl = <HTMLIFrameElement>modalEl.querySelector('iframe');

		videoEl.src = link;
		videoEl.title = name;

		modalEl.dataset['index'] = nextIndex.toString();


	})

	// Отображение модалки с видосом
	$('body').on('click', '.video-trigger', (e:JQuery.ClickEvent) => {
		e.preventDefault();
		let link = <HTMLElement>e.currentTarget;
		let videoModal = M.Modal.getInstance(document.querySelector('#video-modal'));
		let modalEl = <HTMLDivElement>videoModal.el;
		let index = link.dataset['index'];
		let videolink = link.dataset['link'] + "&autoplay=1";
		let videoEl = <HTMLIFrameElement>modalEl.querySelector('#video-frame');
		let name = $(link).parents('.video-entry').find('.title a').text();

		
		videoEl.src = videolink;
		videoEl.title = name;
		modalEl.dataset['index'] = index;
		let youtubeLink = <HTMLLinkElement>modalEl.querySelector('#open-youtube');
		youtubeLink.href = videolink;
		
		videoModal.options.onCloseEnd = () => videoEl.src = '';

		videoModal.open();
	});

	// Скрытие вопроса из общего списка в личном кабинете
	$('body').on('click', '.is_hidden', (e:JQuery.ClickEvent) => {

		let el = <HTMLLinkElement>e.currentTarget;
		let docId = el.dataset['id'];
		let value = el.dataset['hide'];
		let newValue = value == '1' ? null : "1";
		let newClass = newValue == '1' ? 'bx-hide' : 'bx-show';

		let data = {
			docId: docId,
			value: newValue,
			action: 'setPrivacy'
		};

		$.ajax({
			url: '/assets/custom-classes/qa.php',
			dataType: 'json',
			data: data,
			type: 'post',
			success: (response) => {
				M.toast({html: response.message});
				$(el).attr('data-hide', newValue);
				$(el).find('i').attr('class', 'bx ' + newClass);
			},
			error: (error) => {
				M.toast({html: 'Ошибка при выполнении запроса!'});
				console.error(error.responseJSON.message);
			}
		})

		
	})

	// Скрытие тоста при нажатии кнопки
	$('body').on('click', '.toast-close', (e:JQuery.ClickEvent) => {
		let target = <HTMLLinkElement>e.currentTarget;
		let toast = <HTMLElement>$(target).parents('.toast').get(0);
		let toastInstance = M.Toast.getInstance(toast);
		toastInstance.dismiss();
	})

	// Вложенный в запланированные мероприятия слайдер
	if($('#nested').length){
		nestedSlider = new Swiper('#nested', {
			direction: 'vertical',
			pagination: {
				type: 'bullets',
				el: '#nested-pagination',
				clickable: true
			},
			updateOnWindowResize: true,
			mousewheel: true,
			on:{
				init:(slider:Swiper) => {
					setSlideHight(slider);
				}
			}
		});
	}

	// Слайдер запланированных мероприятий на главной
	if($('#plans-slider').length){
		let plansSlider = new Swiper('#plans-slider', {
			pagination: {
				type: 'bullets',
				el: '#plans-pagination',
				clickable: true
			}
		});
	}

	// Слайдер в секции новостей
	if($('#news-section-slider').length){
		let newsSectionSlider = new Swiper('#news-section-slider', {
			spaceBetween: 20,
			breakpoints: {
				400: {
					slidesPerView: 1
				},
				800: {
					slidesPerView: 2
				},
				1300: {
					slidesPerView: 3
				},
				1800: {
					slidesPerView: 4
				}
			},
			speed: 800,
			autoplay: {
				delay: 5000
			},
			pagination: {
				type: 'bullets',
				el: '#news-section-pagination'
			}
		})
	}

	//Search-trigger
	$('body').on('click', '#search-trigger', (e:JQuery.ClickEvent) => {
		e.preventDefault();
		$(e.currentTarget).parents('.container').toggleClass('search-shown').find('input[type="search"]').get(0)?.focus();
	});

	// Закрытие строки поиска по клику мимо
	$('body').on('click', (e:JQuery.ClickEvent) => {

		let path = e.originalEvent?.composedPath();

		if(path){

			let pathArray = Array.from(path);
			let filtered = pathArray.filter(el => {
				let myel = <HTMLElement>el;
				if(myel.classList){
					return myel.classList.contains('search-form') || myel.id == 'search-trigger';
				}
			});

			if(!filtered.length){
				$('.container').removeClass('search-shown');
			}

		}
	})

	//Sidenav
	let sidenav = M.Sidenav.init(document.querySelectorAll('.sidenav'));

	// Вкладки
	let tabs = M.Tabs.init(document.querySelectorAll('.tabs'));

	// Модальные окна
	let modal = M.Modal.init(document.querySelectorAll('.modal'));

	// Отработка ленивых картинок
	let lazy = new Lazy({}, document.querySelectorAll('.lazy'));

	// Календарь на главной
	if($('#air-datepicker').length){
		let calendar = new AirDatepicker('#air-datepicker', {
			inline: true,
		});
	}

	// Обработка поведения Header'а
	setHeader();
	$(window).on('scroll', (e:JQuery.ScrollEvent) => {
		setHeader();
	});

	// Слайдер в модалке задать вопрос
	if($('#question-swiper').length){
		questionsSlider = new Swiper('#question-swiper');
	}

	// Слайдер новостей в шапке главной
	if($('#news-swiper').length){
		let newsSwiper = new Swiper('#news-swiper', {
			spaceBetween: 20,
			pagination: {
				el: '#news-pagination',
				type: 'bullets',
				clickable: true
			},
			breakpoints: {
				300: {
					slidesPerView: 1
				},
				1300: {
					slidesPerView: 2
				},
				1600: {
					slidesPerView: 3
				}
			}
		});
	}

	// Слайдер итогов
	if($('#summary-slider').length){
		let summarySlider = new Swiper('#summary-slider', {
			spaceBetween: 20,
			breakpoints: {
				300: {
					slidesPerView: 1
				},
				600: {
					slidesPerView: 2
				},
				1300: {
					slidesPerView: 3
				}
			},
			pagination: {
				el: "#summary-pagination",
				type: "bullets",
				clickable: true
			}
		})
	}

	// Слайдер партнёров
	if($('#partners-slider').length){
		let partnersSlider = new Swiper('#partners-slider', {
			breakpoints: {
				300: {
					slidesPerView: 2
				},
				800: {
					slidesPerView: 3
				},
				1300: {
					slidesPerView: 4
				},
				1600: {
					slidesPerView: 5
				}
			}
		})
	}

	// Подсветка поля поиска секции вопрос-ответ на главной
	if($('#faq-searcher').length){
		$('#faq-searcher input').on('focus', (e:JQuery.FocusEvent) => {
			$(e.currentTarget).parents('form').addClass('focus');
		});
		$('#faq-searcher input').on('blur', (e:JQuery.BlurEvent) => {
			$(e.currentTarget).parents('form').removeClass('focus');
		});
	}

	// Открытие ответов на вопросы
	if($('.question[data-id]').length){

		// Подсвечиваем первый вопрос по умолчанию
		$('.question:first-of-type').addClass('active');

		$('body').on('click', '.question[data-id]', (e:JQuery.ClickEvent) => {

			$('.dynamic-content').addClass('loading');
			let element = <HTMLElement>e.currentTarget;
			let id = element.dataset['id'];
			let urlString = window.location.origin + "/assets/custom-classes/qa.php";
			let data = {
				'id': id,
				'action': 'getAnswer'
			}

			$.ajax({
				url: urlString,
				data: data,
				type: 'POST',
				dataType: 'json',
				success: (response) => {
					let data = <IDocSuccessResponse>response;
			 		$('.answer-author .name').text(data.author);
			 		$('.answer-author .rank').text(data.rank);
			 		$('.answer-content').html(data.content).scrollTop(0);
					$('.question').removeClass('active');
					$(element).addClass('active');
				},
				error: error => {
					console.error(error);
				},
				complete: () => {
					$('.dynamic-content').removeClass('loading');
				}
			})
		})
	}

	// Слайдер афиши на странице обзора новостей
	if($('#overview-afisha-slider').length){
		let AfishaSlider = new Swiper('#overview-afisha-slider', {
			pagination: {
				el: '#afisha-pagination',
				type: 'bullets',
				clickable: true
			}
		})
	}

	// Слайдер дайджеста
	if($('#digest-slider').length){
		let digestSlider = new Swiper('#digest-slider', {
			spaceBetween: 20,
			breakpoints: {
				300: {
					slidesPerView: 1
				},
				1000: {
					slidesPerView: 2
				},
				1600: {
					slidesPerView: 3
				},
				2000: {
					slidesPerView: 4
				}
			}
		})
	}

	// Авторизация пользователя
	$('body').on('submit', '#login-form', (e:JQuery.SubmitEvent) => {

		e.preventDefault();

		let form = <HTMLFormElement>e.currentTarget;
		let data = $(form).serialize();
		let url = "/assets/custom-classes/auth.php";
		let modal = M.Modal.getInstance(form);

		$.ajax({
			url: url,
			data: data,
			dataType: 'json',
			type: 'POST',
			success: (response) => {
				M.toast({html: response.message});
				setTimeout(() => {
					window.location.reload();
				}, 500);
			},
			error: (error) => {
				M.toast({html: error.responseJSON.message})
			}
		})
	})

	// Выход из учётной записи
	$('body').on('click', '#logout', (e:JQuery.ClickEvent) => {
		let action = {
			'action': 'logout'
		}
		$.ajax({
			url: '/assets/custom-classes/auth.php',
			data: action,
			type: 'post',
			dataType: 'json',
			success: (response) => {
				M.toast({html: response.message});
				setTimeout(() => {
					window.location.reload();
				}, 500);
			},
			error: (error) => {
				M.toast({html: error.responseJSON.message})
			}
		})
	});

	// Регистрация
	$('body').on('submit', '#register-form', (e:JQuery.SubmitEvent) => {

		e.preventDefault();
		let form = <HTMLFormElement>e.currentTarget;
		let data = $(form).serialize();
		let url = "/assets/custom-classes/auth.php";

		$.ajax({
			url: url,
			type: 'POST',
			data: data,
			dataType: 'json',
			success: (response) => {
				M.toast({html: response.message})
			},
			error: (error) => {
				M.toast({html: error.responseJSON.message})
			}
		});

	});

	// Изменение пароля
	$('body').on('click', '#save-password', (e:JQuery.ClickEvent) => {
		let element = e.currentTarget;
		let form = $(element).parents('form');
		let formData = form.serialize();

		$.ajax({
			url: '/assets/custom-classes/auth.php',
			type: 'POST',
			dataType: 'json',
			data: formData,
			success: (response) => {
				M.toast({html: response.message});
				setTimeout(() => {
					window.location.href = "/";
				}, 500);
			},
			error: (error) => {
				M.toast({html: error.responseJSON.message})
			}
		})
	});

	// Задать вопрос (открытие формы)
	$('body').on('click', '#ask_question', (e:JQuery.ClickEvent) => {
		let element = <HTMLElement>e.currentTarget;
		let logged = element.dataset['logged'] === "true";

		if(!logged){
			M.toast(
				{
					html: '<span>Чтобы задать вопрос, нужно войти в свою учётную запись. Войти?</span><a class="bttn-flat modal-trigger toast-close waves-effect waves-light" href="#login-form">Да</a>',
				}
			);
		}else{
			let modalEl = <HTMLElement>document.querySelector('#ask-question');
			let inputFile = <HTMLInputElement>modalEl.querySelector('[type=file]');

			$('#finish').hide();
			$('#send-question').show();

			questionsSlider.enable();
			questionsSlider.slideTo(0, 0);
			questionsSlider.disable();

			inputFile.name = "file[]";
			let modal = M.Modal.getInstance(modalEl);
			modal.open();
		}
	});

	// Задать вопрос (запрос на backend)
	$('body').on('click', '#send-question', (e:JQuery.ClickEvent) => {
		
		let el = e.currentTarget;
		let form = $(el).parents('form');
		// let formData = form.serialize();
		let formData = new FormData(form.get(0));
		let modal = M.Modal.getInstance(<HTMLElement>form.get(0));

		$.ajax({
			url: '/assets/custom-classes/qa.php',
			data: formData,
			dataType: 'json',
			type: 'post',
			processData: false,
			contentType: false,
			cache: false,
			success: (response) => {
				(<HTMLFormElement>form.get(0)).reset();
				questionsSlider.enable();
				questionsSlider.slideNext();
				questionsSlider.disable();

				$('#finish').show();
				$('#send-question').hide();
			},
			error: (error) => {
				M.toast({html: error.responseJSON.message});
			}
		})
	});

	// Отображение/скрытие ответов в профиле
	$('body').on('click', '.profile-question', (e:JQuery.ClickEvent) => {

		let path = Array.from(e.originalEvent?.composedPath());
		let linkEl = path.filter(el => {
			return (<HTMLElement>el).tagName == "A";
		});

		if(!linkEl.length){

			e.preventDefault();
			let el = <HTMLElement>e.currentTarget;
			let answerElement = $(el).next();
	
			if(answerElement.html().trim() != ''){
				answerElement.slideToggle('fast');
			}else{
				M.toast({html: 'Ответ на этот вопрос ещё не найден!'});
			}
		}

	});

	// Переключение избранного
	$('body').on('click', '.fav-trigger', (e:JQuery.ClickEvent) => {
		let element = <HTMLElement>e.currentTarget;
		let docId = element.dataset['id'];
		e.preventDefault();
		
		$.ajax({
			url: '/assets/custom-classes/fav.php',
			type: 'POST',
			dataType: 'json',
			data: {
				'id': docId
			},
			success: (response) => {
				M.toast({html: response.message});
				$(element).attr('class', 'fav-trigger ' + response.newClass);
			},
			error: (error) => {
				M.toast({html: error.responseJSON.message});
				console.error(error);
			}
		})
	});

	// Сохранение данных профиля
	$('body').on('click', '#save-profile', (e:JQuery.ClickEvent) => {
		let element = <HTMLElement>e.currentTarget;
		let $form = $(element).parents('form');
		let formData = $form.serialize();

		$.ajax({
			url: '/assets/custom-classes/auth.php',
			type: 'POST',
			dataType: 'json',
			data: formData,
			success: (response) => {
				M.toast({html: response.message});
			},
			error: (error) => {
				M.toast(error.responseJSON.message);
				console.error(error);
			}
		});
	});

	// Поиск по вопросам
	$('body').on('click', '#question-search-trigger', (e:JQuery.ClickEvent) => {
		e.preventDefault();
		let el = <HTMLElement>e.currentTarget;
		let form = $(el).parents('form').get(0);
		form?.submit();
	});

	// Фокусировка формы по фокусу на элементе формы
	$('body').on('focus', 'input, textarea', (e:JQuery.FocusEvent) => {
		let element = e.currentTarget;
		let form = $(element).parents('form');
		form.addClass('focus');
	});

	// Расфокусировка формы по расфокусировке элемента управления
		// Фокусировка формы по фокусу на элементе формы
	$('body').on('blur', 'input, textarea', (e:JQuery.BlurEvent) => {
		let element = e.currentTarget;
		let form = $(element).parents('form');
		form.removeClass('focus');
	});

	// Сабмит формы по 'Enter' по полю с соответствующим классом
	$('body').on('click', '.submit-trigger', (e:JQuery.ClickEvent) => {
		let form = $(e.currentTarget).parents('form').get(0);
		form?.querySelector("[type=submit]")?.dispatchEvent(new Event('click'));
		form.dispatchEvent(new Event('submit'));
	})
});

function setHeader()
{
	let scrollTop = $('html, body').scrollTop() || 0;
	let extraClass = scrollTop >= 80 ? 'fixed' : '';
	if($('header').length){
		$('header').attr('class', extraClass);
	}
}

function setSlideHight(slider:Swiper){
	let el = slider.el;
	let heights:number[] = [];
	el.querySelectorAll('.swiper-slide').forEach(slide => {
		let slideEl = <HTMLElement>slide;
		$(slideEl).attr('style', 'height: fit-content');
		heights.push(slide.clientHeight);
		$(slideEl).removeAttr('style');
	});

	let max = Math.max.apply(Math, heights);

	slider.slides.forEach(slide => {
		(<HTMLDivElement>slide).style.height = max+'px';
	});

	(<HTMLElement>slider.el.querySelector('.swiper-wrapper')).style.height = max + 'px';

	slider.el.style.height = max + 'px';

}