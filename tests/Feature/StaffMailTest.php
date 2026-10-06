<?php

use App\Models\Make;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Notifications\NewQuoteRequest;
use App\Settings\GeneralSettings;
use App\Support\StaffMail;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function setStaffEmails(?string $list, string $contact = 'info@tocojapan.com'): void
{
    $s = app(GeneralSettings::class);
    $s->notification_emails = $list;
    $s->contact_email = $contact;
    $s->save();
}

it('sends staff alerts to the configured list, falling back to the contact email', function () {
    setStaffEmails(null);
    expect(StaffMail::recipients())->toBe(['info@tocojapan.com']);

    setStaffEmails("sales@example.com, first@toco-int.com\nbad-address; sales@example.com");
    expect(StaffMail::recipients())->toBe(['sales@example.com', 'first@toco-int.com']);
});

it('emails sales when a customer requests a quote and when they reply', function () {
    Notification::fake();
    setStaffEmails('sales@example.com');
    $make = Make::create(['slug' => 'toyota', 'name' => 'Toyota']);
    $model = VehicleModel::create(['make_id' => $make->id, 'slug' => 'hiace', 'name' => 'Hiace']);
    $vehicle = Vehicle::factory()->create(['make_id' => $make->id, 'vehicle_model_id' => $model->id, 'status' => 'published', 'stock_no' => 'E02059']);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('quotes.store', $vehicle->slug), [
        'contact_name' => 'Jane Buyer', 'contact_email' => 'jane@example.com', 'message' => 'Best price to Mombasa?',
    ])->assertRedirect();

    Notification::assertSentOnDemand(NewQuoteRequest::class, function ($n, $channels, AnonymousNotifiable $notifiable) {
        $mail = $n->toMail($notifiable);

        return $notifiable->routes['mail'] === ['sales@example.com']
            && $mail->replyTo[0][0] === 'jane@example.com'
            && str_contains($mail->subject, 'New quote request');
    });

    $quote = $user->quotes()->firstOrFail();
    $this->actingAs($user)->post(route('quotes.reply', $quote), ['body' => 'Still available?'])->assertRedirect();
    Notification::assertSentOnDemandTimes(NewQuoteRequest::class, 2);
});
