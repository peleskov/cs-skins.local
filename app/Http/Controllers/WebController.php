<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Doc;

class WebController extends Controller
{
    /**
     * Главная страница
     */
    public function home()
    {
        return view('home');
    }

    /**
     * Маркетплейс
     */
    public function marketplace(Request $request)
    {
        // TODO: Реализовать фильтрацию и пагинацию
        return view('marketplace');
    }

    /**
     * Страница товара
     */
    public function item($id)
    {
        // TODO: Получить товар по ID
        return view('item');
    }

    /**
     * Корзина
     */
    public function cart()
    {
        return view('cart');
    }

    /**
     * FAQ
     */
    public function faq()
    {
        $categories = FaqCategory::ordered()->get();
        $faqs = Faq::with('category')->active()->ordered()->get();
        
        // Группируем FAQ по категориям (включая без категории)
        $faqsByCategory = $faqs->groupBy(function($faq) {
            return $faq->category ? $faq->category->slug : 'no_category';
        });
        
        return view('faq', compact('categories', 'faqsByCategory'));
    }

    /**
     * Контакты
     */
    public function contact()
    {
        return view('contact');
    }

    /**
     * Документ
     */
    public function doc($slug)
    {
        $doc = Doc::where('slug', $slug)->firstOrFail();
        return view('doc', compact('doc'));
    }

    /**
     * Переключение языка
     */
    public function setLocale($locale)
    {
        if (in_array($locale, ['ru', 'en'])) {
            session(['locale' => $locale]);
            app()->setLocale($locale);
        }
        
        return redirect()->back();
    }
}
