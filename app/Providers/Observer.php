<?php

namespace App\Providers;

use App\Models\Banking\Account;
use App\Models\Banking\Transaction;
use App\Models\Common\Contact;
use App\Models\Document\Document;
use App\Models\Document\DocumentItem;
use App\Models\Setting\Category;
use Illuminate\Support\ServiceProvider as Provider;

class Observer extends Provider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Contact::observe('App\Observers\GritchiContact');
        Account::observe('App\Observers\GritchiFinance');
        Category::observe('App\Observers\GritchiFinance');
        Document::observe('App\Observers\GritchiFinance');
        DocumentItem::observe('App\Observers\GritchiFinance');
        Transaction::observe('App\Observers\GritchiFinance');
        Transaction::observe('App\Observers\Transaction');
    }
}
