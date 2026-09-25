<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Drivers\Newsletter\MailchimpNewsletterProvider;
use App\Exceptions\AlreadySubscribedException;
use MailchimpMarketing\Api\ListsApi;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/** The subscribe() decision on what the audience already knows about the address. */
class MailchimpNewsletterProviderTest extends TestCase
{
    private MockInterface $lists;

    private MailchimpNewsletterProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mailchimp.audience_id' => 'list1', 'services.mailchimp.merge_fields' => ['name' => 'FNAME', 'surname' => 'LNAME', 'phone' => 'PHONE', 'customer_type' => '', 'company' => 'MMERGE7', 'activity' => 'MMERGE6']]);
        /** @var ListsApi&MockInterface $lists */
        $lists = Mockery::mock(ListsApi::class);
        $this->lists = $lists;
        $this->provider = new MailchimpNewsletterProvider($lists);
    }

    public function test_a_new_address_is_added_as_subscribed_with_the_mapped_merge_fields(): void
    {
        $this->lists->shouldReceive('getListMember')->once()->andThrow(new RuntimeException('[404] Resource Not Found'));
        $this->lists->shouldReceive('addListMember')->once()->with('list1', [
            'email_address' => 'new@example.com',
            'status' => 'subscribed',
            'merge_fields' => ['FNAME' => 'Anna', 'LNAME' => 'Rossi', 'PHONE' => '0123', 'MMERGE7' => 'ACME'],
        ]);
        $this->provider->subscribe(['email' => 'new@example.com', 'name' => 'Anna', 'surname' => 'Rossi', 'phone' => '0123', 'customer_type' => 'Azienda', 'company' => 'ACME', 'activity' => '']);
    }

    public function test_a_subscribed_or_pending_member_is_reported_as_already_subscribed(): void
    {
        foreach (['subscribed', 'pending'] as $status) {
            $this->lists->shouldReceive('getListMember')->once()->andReturn((object) ['status' => $status]);
            $this->lists->shouldNotReceive('addListMember');
            $this->lists->shouldNotReceive('setListMember');
            try {
                $this->provider->subscribe(['email' => 'old@example.com']);
                $this->fail('expected AlreadySubscribedException for '.$status);
            } catch (AlreadySubscribedException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_an_unsubscribed_member_is_put_back_as_pending_for_the_opt_in_mail(): void
    {
        $this->lists->shouldReceive('getListMember')->once()->andReturn((object) ['status' => 'unsubscribed']);
        $this->lists->shouldNotReceive('addListMember');
        $this->lists->shouldReceive('setListMember')->once()->with('list1', md5('back@example.com'), [
            'email_address' => 'back@example.com',
            'status' => 'pending',
            'merge_fields' => ['FNAME' => 'Bruno'],
        ]);
        $this->provider->subscribe(['email' => 'back@example.com', 'name' => 'Bruno']);
    }
}
