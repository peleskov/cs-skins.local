<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // View Composer для навигации, профильных табов и других элементов
        // Делаем данные доступными во всех шаблонах
        View::composer('*', function ($view) {
            // Главное меню навигации
            $mainNavigation = [
                'marketplace' => [
                    'title' => __('navigation.main.marketplace'),
                    'route' => 'marketplace',
                    'order' => 1
                ],
                'cases' => [
                    'title' => __('navigation.main.cases'),
                    'route' => '#',
                    'order' => 2
                ],
                'auction' => [
                    'title' => __('navigation.main.auction'),
                    'route' => '#',
                    'order' => 3
                ],
                'faq' => [
                    'title' => __('navigation.main.faq'),
                    'route' => 'faq',
                    'order' => 4
                ],
                'contact' => [
                    'title' => __('navigation.main.contact'),
                    'route' => 'contact',
                    'order' => 5
                ]
            ];

            // Профильные табы
            $profileTabs = [
                'profile' => [
                    'title' => __('profile.tabs.profile'),
                    'icon' => 'ri-user-3-line',
                    'order' => 1
                ],
                'trading' => [
                    'title' => __('profile.tabs.trading'),
                    'icon' => 'ri-shopping-bag-3-line',
                    'order' => 2
                ],
                'inventory' => [
                    'title' => __('profile.tabs.inventory'),
                    'icon' => 'ri-treasure-map-line',
                    'order' => 3
                ],
                'favorites' => [
                    'title' => __('profile.tabs.favorites'),
                    'icon' => 'ri-heart-line',
                    'order' => 4
                ],
                'orders' => [
                    'title' => __('profile.tabs.orders'),
                    'icon' => 'ri-shopping-cart-2-line',
                    'order' => 5
                ],
                'sales' => [
                    'title' => __('profile.tabs.sales'),
                    'icon' => 'ri-money-dollar-circle-line',
                    'order' => 6
                ],
                'auctions' => [
                    'title' => __('profile.tabs.auctions'),
                    'icon' => 'ri-store-2-line',
                    'order' => 7
                ],
                'balance' => [
                    'title' => __('profile.tabs.balance'),
                    'icon' => 'ri-bank-card-line',
                    'order' => 8
                ],
                'settings' => [
                    'title' => __('profile.tabs.settings'),
                    'icon' => 'ri-settings-3-line',
                    'order' => 9
                ]
            ];

            // Информация для футера
            $footerData = [
                'company' => [
                    'name' => __('navigation.footer.company_info.name'),
                    'inn' => __('navigation.footer.company_info.inn'),
                    'address' => __('navigation.footer.company_info.address'),
                    'email' => __('navigation.footer.company_info.email')
                ],
                'copyright' => __('navigation.footer.copyright'),
                'documents_title' => __('navigation.footer.documents'),
                'useful_links_title' => __('navigation.footer.useful_links')
            ];

            // Сортируем по order и передаем в шаблон
            uasort($mainNavigation, fn($a, $b) => $a['order'] <=> $b['order']);
            uasort($profileTabs, fn($a, $b) => $a['order'] <=> $b['order']);
            
            $view->with([
                'mainNavigation' => $mainNavigation,
                'profileTabs' => $profileTabs,
                'footerData' => $footerData
            ]);
        });
    }
}
