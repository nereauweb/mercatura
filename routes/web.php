<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

/* PUBLIC ROUTES */
// Route::get('/test', [\App\Http\Livewire\Example::class, 'render'])->name('frontend.test');
// HOME
Route::get('/', [FrontendContentController::class, 'index'])->name('frontend.home');
// CONTATTACI
Route::get('/contattaci', [FrontendContactsController::class, 'index'])->name('frontend.contacts.index');
Route::post('/contattaci/invia', [FrontendContactsController::class, 'send'])->name('frontend.contacts.send');
// NEWSLETTER
Route::get('/newsletter', [FrontendContactsController::class, 'newsletter_subscribe_form'])->name('frontend.newsletter.index');
Route::post('/newsletter/registrati', [FrontendContactsController::class, 'newsletter_subscribe'])->name('frontend.newsletter.register');
Route::post('/newsletter/registrati/successo', [FrontendContactsController::class, 'newsletter_subscribe_success'])->name('frontend.newsletter.registered');
// AUTH
Route::get('/registrati', [FrontendLoginController::class, 'register_form'])->name('frontend.auth.register');
Route::post('/registrati', [FrontendLoginController::class, 'register'])->name('frontend.auth.register.submit');
Route::post('/password-reset/request', [FrontendLoginController::class, 'request_reset_password'])->name('frontend.password_reset.request');
Route::get('/reset-password/{token}', function (string $token) {
    return view('frontend.auth.password-reset', ['token' => $token]);
})->middleware('guest')->name('password.reset');
Route::post('/password-reset/submit', [FrontendLoginController::class, 'reset_password'])->name('frontend.password_reset.submit');
Route::get('/accedi', [FrontendLoginController::class, 'index'])->name('frontend.auth.login');
Route::post('/login', [FrontendLoginController::class, 'attempt_login'])->name('frontend.login.attempt');
Route::get('/logout', [FrontendLoginController::class, 'logout'])->name('frontend.auth.logout');
// CONTENUTI
Route::get('/contenuti/{slug}', [FrontendContentController::class, 'page'])->name('frontend.contents.page');
// BLOG
Route::get('/blog', [FrontendBlogController::class, 'index'])->name('frontend.blog.index');
Route::get('/blog/{slug}', [FrontendBlogController::class, 'show'])->name('frontend.blog.show');
// 404 NOT FOUND
Route::get('/404', [FrontendContentController::class, 'notFound'])->name('frontend.not_found');
Route::get('/variante/{id}/cover', [FrontendListController::class, 'get_variant_cover'])->name('frontend.variant.cover');
// RICERCA
Route::get('/search/suggest/{terms}', [FrontendSearchController::class, 'suggest'])->name('frontend.search.suggest');
Route::get('/search/{terms}', [FrontendSearchController::class, 'get_products'])->name('frontend.search.products');
// CATEGORIE E PRODOTTI
Route::get('/prodotti', [FrontendListController::class, 'list_page'])->name('frontend.product.list');
Route::get('/categorie/id/{id}', [FrontendListController::class, 'index'])->name('frontend.category.show.by_id');
Route::get('/categorie/{slug}', [FrontendListController::class, 'show'])->name('frontend.category.show.by_slug');
Route::get('/prodotti/marchi/{brand}', [FrontendListController::class, 'brand'])->name('frontend.product.list.brand');
Route::get('/prodotti/id/{id}', [FrontendProductController::class, 'show_by_id'])->name('frontend.product.show.by_id');
Route::get('/prodotti/{slug}', [FrontendProductController::class, 'show_by_slug'])->name('frontend.product.show.by_slug');
Route::get('/prodotti/id/{id}/{sku}', [FrontendProductController::class, 'show_variant_by_id'])->name('frontend.product.show.by_id.variant');
Route::get('/prodotti/{slug}/{sku}', [FrontendProductController::class, 'show_variant_by_slug'])->name('frontend.product.show.by_slug.variant');
Route::post('/prodotti/bestsellers', [FrontendProductController::class, 'get_bestsellers'])->name('frontend.product.get_bestsellers');
Route::post('/prodotti/configuratore', [FrontendProductController::class, 'get_configurator'])->name('frontend.product.get_configurator');
Route::post('/prodotti/configuratore/articoli', [FrontendProductController::class, 'build_articles_request'])->name('frontend.product.build_articles_request');
Route::post('/prodotti/configuratore/stampa/pdf', [FrontendProductController::class, 'print_summary'])->name('frontend.product.print_summary');
Route::get('/prodotti/configuratore/stampa/pdf', [FrontendContentController::class, 'index'])->name('frontend.product.pdf_summary');
Route::post('/prodotti/stock', [FrontendProductController::class, 'get_product_variants_stock'])->name('frontend.product.get.variants_stock');
Route::post('/prodotti/personalizzazione/immagine_dimensioni', [FrontendProductController::class, 'customizationAreas'])->name('frontend.product.get.printing_image_and_sizes');
Route::post('/prodotti/personalizzazione/colori', [FrontendProductController::class, 'customizationOptions'])->name('frontend.product.get.options');
// PREVENTIVO
Route::get('/preventivo/', [FrontendQuotationController::class, 'show'])->name('frontend.quotation.show');
Route::get('/preventivo/{id}/configura', [FrontendQuotationController::class, 'configure'])->name('frontend.quotation.configure');
Route::post('preventivo/articolo/aggiungi', [FrontendQuotationController::class, 'create'])->name('frontend.quotation.add');
Route::delete('/preventivo/{id}/elimina', [FrontendQuotationController::class, 'destroy'])->name('frontend.quotation.destroy');
Route::post('preventivo/invia', [FrontendQuotationController::class, 'store'])->name('frontend.quotation.store');
Route::get('/preventivo/{id}/modifica', [FrontendQuotationController::class, 'edit'])->name('frontend.quotation.edit');
Route::put('/preventivo/{id}/aggiorna', [FrontendQuotationController::class, 'update'])->name('frontend.quotation.update');
// CARRELLO E CHECKOUT
Route::get('/carrello/riepilogo', [FrontendCartController::class, 'cart'])->name('frontend.cart.index');
Route::post('/carrello/aggiungi', [FrontendCartController::class, 'add_to_cart'])->name('frontend.cart.add');
Route::post('/carrello/grafica', [FrontendCartController::class, 'uploadArtwork'])->name('frontend.cart.artwork');
Route::delete('/carrello/rimuovi/{id}', [FrontendCartController::class, 'remove_from_cart'])->name('frontend.cart.remove');

Route::post('/carrello/procedi', [FrontendCartController::class, 'checkout'])->name('frontend.cart.checkout');
Route::get('/checkout/account', [FrontendCartController::class, 'account'])->name('frontend.checkout.account');
Route::post('/checkout/account/login', [FrontendCartController::class, 'attempt_login'])->name('frontend.checkout.account.login');
Route::get('/checkout/account/register', [FrontendCartController::class, 'register_form'])->name('frontend.checkout.account.registration');
Route::post('/checkout/account/register', [FrontendCartController::class, 'register'])->name('frontend.checkout.account.register');

Route::get('/ordine/{id}/pagamento/stripe/successo', [FrontendOrderController::class, 'stripe_payment_success'])->name('frontend.payment.stripe.success');
Route::get('/ordine/{id}/pagamento/stripe/cancellazione', [FrontendOrderController::class, 'stripe_payment_cancel'])->name('frontend.payment.stripe.cancel');
Route::get('/ordine/{id}/pagamento/paypal/successo', [FrontendOrderController::class, 'paypal_payment_success'])->name('frontend.payment.paypal.success');
Route::get('/ordine/{id}/pagamento/paypal/cancellazione', [FrontendOrderController::class, 'paypal_payment_cancel'])->name('frontend.payment.paypal.cancel');

/* Logged routes */
Route::group(['middleware' => 'auth'], function () {

    Route::post('/checkout/pagamento', [FrontendCartController::class, 'payment'])->name('frontend.checkout.payment');
    Route::post('/checkout/completa', [FrontendCartController::class, 'store_order'])->name('frontend.checkout.finalize');
    Route::get('/checkout/registrato', [FrontendOrderController::class, 'bank_transfer_order_registered'])->name('frontend.checkout.registered');

    Route::get('/utente', [FrontendAuthController::class, 'index'])->name('frontend.auth.index');
    Route::get('/area-riservata', [FrontendAuthController::class, 'index'])->middleware(['auth', 'redirect.if.admin'])->name('frontend.auth.reserved_area');
    Route::get('/profilo', [FrontendAuthController::class, 'profile'])->name('frontend.auth.profile');
    Route::post('/profilo/utente/aggiorna', [FrontendAuthController::class, 'update_user'])->name('frontend.auth.profile.user.update');
    Route::post('/profilo/cliente/aggiorna', [FrontendAuthController::class, 'update_customer'])->name('frontend.auth.profile.customer.update');

    Route::get('/preventivi', [FrontendQuotationController::class, 'index'])->name('frontend.quotation.index');

    Route::get('/ordini', [FrontendOrderController::class, 'list'])->name('frontend.auth.order.list');
    Route::get('/ordine/{id}', [FrontendOrderController::class, 'show'])->name('frontend.auth.order.show');
    Route::put('/ordine/personalizzazione/file{id}', [FrontendOrderController::class, 'uploadCustomizationFile'])
        ->middleware('throttle:20,1')
        ->name('customer.order.printings.upload-image');
    Route::post('/ordine/{id}/upload-file', [FrontendOrderController::class, 'uploadOrderImage'])
        ->middleware('throttle:20,1')
        ->name('customer.order.upload-file');
    Route::delete('/ordine/{id}/delete-file/{mediaId}', [FrontendOrderController::class, 'deleteOrderImage'])
        ->middleware('throttle:20,1')
        ->name('customer.order.delete-file');

    Route::get('/messaggi', [FrontendContactsController::class, 'list'])->name('frontend.contacts.list');
    Route::get('/messaggi/{id}', [FrontendContactsController::class, 'show'])->name('frontend.contacts.show');

});

// The admin panel is Filament (app/Providers/Filament/AdminPanelProvider.php); its routes are registered by the panel.
