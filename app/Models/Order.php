<?php

namespace App\Models;

use App\Contracts\TransactionalMailer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Order extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $casts = ['read_at' => 'datetime'];

    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'customer_id',
        'items_price',
        'delivery_cost',
        'total_price',
        'total_tax',
        'total_taxed_price',
        'address',
        'province',
        'city',
        'zip_code',
        'country',
        'notes',
        'tracking_code',
        'status',	// enum: 'draft','requested','payment_notified','paid','processing','delivering', 'complete', 'cancelled'
        'payment_method', // enum: 'bank_transfer','stripe','paypal'
        'payment_transaction',
        'payment_status', // enum: 'unpaid','paid','refunded'
        'read_at',
    ];

    public static $status_fields = [
        'Bozza' => 'draft',
        'Richiesto' => 'requested',
        'Segnalato pagamento' => 'payment_notified',
        'Pagato' => 'paid',
        'In preparazione' => 'processing',
        'In consegna' => 'delivering',
        'Completato' => 'complete',
        'Cancellato' => 'cancelled',
    ];

    public static $status_names = [
        'draft' => 'Bozza',
        'requested' => 'Richiesto',
        'payment_notified' => 'Segnalato pagamento',
        'paid' => 'Pagato',
        'processing' => 'In preparazione',
        'delivering' => 'In consegna',
        'complete' => 'Completato',
        'cancelled' => 'Cancellato',
    ];

    public static $payment_status_fields = [
        'Non pagato' => 'unpaid',
        'Pagato' => 'paid',
        'Rimborsato' => 'refunded',
    ];

    public static $payment_status_names = [
        'unpaid' => 'Non pagato',
        'paid' => 'Pagato',
        'refunded' => 'Rimborsato',
    ];

    public static $payment_method_fields = [
        'Bonifico' => 'bank_transfer',
        'Stripe' => 'stripe',
        'Paypal' => 'paypal',
    ];

    public static $payment_method_names = [
        'bank_transfer' => 'Bonifico',
        'stripe' => 'Stripe',
        'paypal' => 'Paypal',
    ];

    public function items(): HasMany
    {
        return $this->hasMany('App\Models\OrderItem', 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function getCustomerAttribute()
    {
        // Controlla se la relazione è già caricata
        if ($this->relationLoaded('customer')) {
            return $this->getRelation('customer');
        }

        // Carica la relazione SENZA chiamare l'accessor
        $customer = $this->getRelationValue('customer');

        // Se non esiste, creala
        if (! $customer) {
            $customer = $this->customer()->create([
                'user_id' => $this->user_id,
                'email' => $this->user->email ?? 'notfound@mail.no',
            ]);
            $this->setRelation('customer', $customer);
        }

        return $customer;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function mail_export_items()
    {
        $order_items = [];
        foreach ($this->items as $order_item) {
            $printings = [];
            foreach ($order_item->printings as $printing) {
                array_push($printings, $printing['printing_label']);
            }
            $quantities = [];
            foreach ($order_item->articles as $article) {
                array_push($quantities, $article['quantity'].' x '.$article['article_sku'].' '.$article['article_size_label'].' '.$article['article_color_label']);
            }
            array_push($order_items, [
                'product_sku' => $order_item->product_sku,
                'product_name' => $order_item->product_name,
                'product_image_url' => url('/').str_replace(url('/'), '', $order_item->product_image_url),
                'quantity' => $order_item->quantity,
                'article_quantities' => implode(', ', $quantities),
                'price' => number_format($order_item->price, 2, ',', '.'),
                'printings' => empty($printings) ? '' : 'Personalizzazioni: '.implode(', ', $printings),
            ]);
        }

        return $order_items;
    }

    public function send_notification($action, $to)
    {
        $mailer = app(TransactionalMailer::class);
        $mailer->attribute('customer_company', $this->customer->company);
        $mailer->attribute('customer_name', $this->customer->name);
        $mailer->attribute('customer_surname', $this->customer->surname);
        $mailer->attribute('customer_email', $this->customer->email);
        $mailer->attribute('customer_phone', $this->customer->phone);

        $mailer->attribute('customer_type', $this->customer->customer_type);
        $mailer->attribute('customer_activity', $this->customer->activity);
        $mailer->attribute('customer_tax_code', $this->customer->tax_code);
        $mailer->attribute('customer_vat_code', $this->customer->vat_code);
        $mailer->attribute('customer_sdi_code', $this->customer->sdi_code);
        $mailer->attribute('customer_ipa_code', $this->customer->ipa_code);
        $mailer->attribute('customer_cig_code', $this->customer->cig_code);
        $mailer->attribute('customer_pec', $this->customer->pec);

        $mailer->attribute('order_id', $this->id);
        $mailer->attribute('order_date', date('d/m/Y H:i', strtotime($this->created_at)));
        $mailer->attribute('order_status', Order::$status_names[$this->status] ?? $this->status);
        $mailer->attribute('payment_status', Order::$payment_status_names[$this->payment_status] ?? $this->payment_status);
        $mailer->attribute('order_tracking_code', $this->tracking_code);
        $mailer->attribute('order_items_price', number_format($this->items_price, 2, ',', '.'));
        $mailer->attribute('order_delivery_cost', number_format($this->delivery_cost, 2, ',', '.'));
        $mailer->attribute('order_total_price', number_format($this->total_price, 2, ',', '.'));
        $mailer->attribute('order_total_tax', number_format($this->total_tax, 2, ',', '.'));
        $mailer->attribute('order_total_taxed_price', number_format($this->total_taxed_price, 2, ',', '.'));
        $mailer->attribute('order_address', $this->address);
        $mailer->attribute('order_province', $this->province);
        $mailer->attribute('order_city', $this->city);
        $mailer->attribute('order_zip_code', $this->zip_code);
        $mailer->attribute('order_country', $this->country);
        $mailer->attribute('order_notes', $this->notes);
        $mailer->attribute('order_tracking_code', $this->tracking_code);
        $mailer->attribute('order_items', $this->mail_export_items());
        switch ($action) {
            case 'stored':
                if ($to == 'user') {
                    $mailer->to($this->customer->email);
                    $mailer->send('order_stored_user');
                }
                if ($to == 'admin') {
                    $mailer->to(config('emails.merchant'));
                    $mailer->send('order_stored_admin');
                }
                break;
            case 'payed':
                if ($to == 'user') {
                    $mailer->to($this->customer->email);
                    $mailer->send('order_paid_user');
                }
                if ($to == 'admin') {
                    $mailer->to(config('emails.merchant'));
                    $mailer->send('order_paid_admin');
                }
                break;
            case 'updated':
                $mailer->to($this->customer->email);
                $mailer->send('order_updated');
                break;
            case 'sent':
                $mailer->to($this->customer->email);
                $mailer->send('order_sent');
                break;
            case 'cancelled':
                $mailer->to($this->customer->email);
                $mailer->send('order_cancelled');
                break;
        }
        $mailer->reset();

        return true;
    }
}
