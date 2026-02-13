<?php

namespace App\Http\Controllers;

use Exception;
use Log;
use Illuminate\Http\Request;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Doc;
use App\Models\Listing;
use App\Models\AdBanner;
use App\Models\CaseModel;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redis;

class WebController extends Controller
{
    /**
     * Главная страница
     */
    public function home()
    {
        $onlineSellerIds = $this->getOnlineSellerIds();

        $featuredListings = Listing::with(['seller'])
            ->active()
            ->where('price', '>', 0)
            ->whereIn('seller_id', $onlineSellerIds)
            ->inRandomOrder()
            ->limit(12)
            ->get();

        $totalListings = Listing::active()
            ->where('price', '>', 0)
            ->whereIn('seller_id', $onlineSellerIds)
            ->count();

        $hasMorePages = $totalListings > 12;

        // Инициализируем переменные для marketplace-section
        $seller = null;
        $sellerStats = null;

        // Получаем активный баннер
        $adBanner = AdBanner::where('active', true)->first();

        // Получаем активные кейсы
        $cases = CaseModel::active()->limit(5)->get();

        return view('home', compact('featuredListings', 'totalListings', 'hasMorePages', 'seller', 'sellerStats', 'adBanner', 'cases'));
    }

    /**
     * Маркетплейс
     */
    public function marketplace(Request $request)
    {
        // TODO: Реализовать фильтрацию и пагинацию
        return view('marketplace.index');
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
     * Отправка формы контактов
     */
    public function contactSend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'message' => 'required|string|max:2000',
        ], [
            'first_name.required' => 'Поле "Имя" обязательно для заполнения',
            'first_name.max' => 'Имя не должно превышать 255 символов',
            'last_name.required' => 'Поле "Фамилия" обязательно для заполнения',
            'last_name.max' => 'Фамилия не должна превышать 255 символов',
            'email.required' => 'Поле "Email" обязательно для заполнения',
            'email.email' => 'Введите корректный email адрес',
            'email.max' => 'Email не должен превышать 255 символов',
            'phone.max' => 'Номер телефона не должен превышать 20 символов',
            'message.required' => 'Поле "Сообщение" обязательно для заполнения',
            'message.max' => 'Сообщение не должно превышать 2000 символов',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Пожалуйста, исправьте ошибки в форме');
        }

        try {
            // Получаем список email из настроек
            $emailsRaw = SiteSetting::get('contact_emails');
            $emails = array_filter(array_map('trim', explode(',', $emailsRaw)));

            if (empty($emails)) {
                Log::warning('Contact form: настройка contact_emails пуста, письмо не отправлено');
                return back()->with('success', 'Ваше сообщение успешно отправлено! Мы свяжемся с вами в ближайшее время.');
            }

            // Отправка email администраторам
            Mail::send('emails.contact', [
                'firstName' => $request->first_name,
                'lastName' => $request->last_name,
                'userEmail' => $request->email,
                'phone' => $request->phone,
                'userMessage' => $request->message,
            ], function ($message) use ($request, $emails) {
                $message->to($emails)
                        ->subject('Новое сообщение с сайта - от ' . $request->first_name . ' ' . $request->last_name)
                        ->replyTo($request->email, $request->first_name . ' ' . $request->last_name);
            });

            return back()->with('success', 'Ваше сообщение успешно отправлено! Мы свяжемся с вами в ближайшее время.');

        } catch (Exception $e) {
            Log::error('Contact form error: ' . $e->getMessage());
            return back()
                ->withInput()
                ->with('error', 'Произошла ошибка при отправке сообщения. Пожалуйста, попробуйте позже.');
        }
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

    /**
     * Получение списка онлайн продавцов из Redis
     */
    private function getOnlineSellerIds(): array
    {
        Redis::zremrangebyscore('online_sellers', '-inf', now()->timestamp);
        $ids = Redis::zrangebyscore('online_sellers', now()->timestamp, '+inf');
        return !empty($ids) ? $ids : [0];
    }
}
