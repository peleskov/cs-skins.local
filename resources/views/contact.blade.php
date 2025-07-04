@extends('layouts.app')

@section('title', 'Контакты - Связаться с нами')

@section('content')
@include('partials.breadcrumbs', ['title' => 'Связаться с нами'])

<!-- contact section starts -->
<section class="section-b-space">
    <div class="container">
        <div class="title animated-title">
            <div class="loader-line"></div>
            <div class="d-flex align-items-center justify-content-between flex-wrap w-100">
                <div>
                    <h2>Контактная информация</h2>
                    <h6>
                        Свяжитесь с нами, если у вас есть какие-либо вопросы или просто хотите поздороваться.
                    </h6>
                </div>
            </div>
        </div>
        <div class="contact-detail">
            <div class="row g-4">
                <div class="col-xxl-3 col-md-6">
                    <div class="contact-detail-box">
                        <div class="contact-icon">
                            <i class="ri-building-fill"></i>
                        </div>
                        <div>
                            <div class="contact-detail-title">
                                <h4>ООО "Скинс"</h4>
                            </div>
                            <div class="contact-detail-contain">
                                <p>ИНН: 1234567890</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-3 col-md-6">
                    <div class="contact-detail-box">
                        <div class="contact-icon">
                            <i class="ri-map-pin-fill"></i>
                        </div>
                        <div>
                            <div class="contact-detail-title">
                                <h4>Адрес</h4>
                            </div>
                            <div class="contact-detail-contain">
                                <p>г. Москва, ул. Примерная, д. 1</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-3 col-md-6">
                    <div class="contact-detail-box">
                        <div class="contact-icon">
                            <i class="ri-mail-open-fill"></i>
                        </div>
                        <div>
                            <div class="contact-detail-title">
                                <h4>Email</h4>
                            </div>
                            <div class="contact-detail-contain">
                                <p>info@cs-skins.pro</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-xl-8">
                <div class="contact-form">
                    <form class="row g-3">
                        <div class="col-md-6">
                            <label for="inputFirstname" class="form-label mt-0">Имя</label>
                            <input type="text" class="form-control" id="inputFirstname"
                                placeholder="Введите ваше имя">
                        </div>
                        <div class="col-md-6">
                            <label for="inputLastname" class="form-label mt-0">Фамилия</label>
                            <input type="text" class="form-control" id="inputLastname"
                                placeholder="Введите вашу фамилию">
                        </div>
                        <div class="col-md-6">
                            <label for="inputEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="inputEmail" placeholder="Введите ваш email">
                        </div>
                        <div class="col-md-6">
                            <label for="inputPhone" class="form-label">Номер телефона</label>
                            <input type="tel" class="form-control" id="inputPhone" placeholder="Введите ваш номер телефона">
                        </div>
                        <div class="col-md-12">
                            <label for="inputtext" class="form-label">Как мы можем вам помочь?</label>
                            <textarea class="form-control" id="inputtext" rows="3"
                                placeholder="Здравствуйте, я хотел бы...."></textarea>
                        </div>
                    </form>
                    <div class="buttons d-flex align-items-center justify-content-end gap-3">
                        <a href="index.html" class="btn theme-btn mt-0">ОТПРАВИТЬ</a>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d288661.61602282553!2d37.055623203146695!3d55.581668257562164!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x46b54afc73d4b0c9%3A0x3d44d6cc5757cf4c!2z0JzQvtGB0LrQstCwLCDQoNC-0YHRgdC40Y8!5e0!3m2!1sru!2ses!4v1751614563127!5m2!1sru!2ses"
                    width="600" height="450" class="contact-map border-0 w-100 h-100" allowfullscreen=""
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </div>
</section>
<!-- contact section end -->

@endsection