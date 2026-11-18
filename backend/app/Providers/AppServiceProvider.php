<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\Notification;
use App\Models\Payable;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\CashRegisterPolicy;
use App\Policies\ClientPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\PayablePolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProfessionalPolicy;
use App\Policies\ProfessionalSchedulePolicy;
use App\Policies\ReceivablePolicy;
use App\Policies\SalePolicy;
use App\Policies\ServiceCategoryPolicy;
use App\Policies\ServicePolicy;
use App\Policies\SettingPolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Professional::class, ProfessionalPolicy::class);
        Gate::policy(ProfessionalSchedule::class, ProfessionalSchedulePolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(ServiceCategory::class, ServiceCategoryPolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Payable::class, PayablePolicy::class);
        Gate::policy(Receivable::class, ReceivablePolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(CashRegister::class, CashRegisterPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
    }
}
