<?php

namespace Functional\Finance\Http\Controllers;

use Foutraz\GoCardlessBank\GoCardlessManager;
use Functional\Finance\Actions\FindOrCreateGoCardlessConnection;
use Functional\Finance\Exceptions\GoCardlessCallbackDeniedException;
use Functional\Finance\Exceptions\GoCardlessNotConnectedException;
use Functional\Finance\Jobs\SyncBankAccountsJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

class GoCardlessConnectionController
{
    public function __construct(private GoCardlessManager $manager) {}

    /**
     * Redirect the user to the GoCardless bank selection page after creating a requisition.
     */
    public function connect(Request $request): SymfonyRedirectResponse
    {
        $token = $this->manager->auth()->newToken();
        $this->manager->setToken($token->accessToken);

        $agreement = $this->manager->requisitions()->createAgreement([
            'institution_id' => config('finance.gocardless.institution_id'),
            'max_historical_days' => 90,
        ]);

        $requisition = $this->manager->requisitions()->createRequisition(
            (string) config('finance.gocardless.institution_id'),
            route('finance.gocardless.callback'),
            $agreement['id'] ?? null,
        );

        $request->session()->put('gocardless_requisition', $requisition->id);

        return redirect()->away($requisition->link);
    }

    /**
     * Handle the GoCardless callback by storing the connection and dispatching a bank account sync.
     *
     * @throws GoCardlessCallbackDeniedException
     */
    public function callback(Request $request, FindOrCreateGoCardlessConnection $finder): RedirectResponse
    {
        $ref = $request->query('ref');
        $sessionRef = $request->session()->pull('gocardless_requisition');

        if ($request->has('error') || $ref === null || $sessionRef === null || ! hash_equals((string) $sessionRef, (string) $ref)) {
            throw new GoCardlessCallbackDeniedException;
        }

        $token = $this->manager->auth()->newToken();
        $this->manager->setToken($token->accessToken);

        $requisition = $this->manager->requisitions()->getRequisition((string) $ref);

        if ($requisition->status !== 'LN') {
            throw new GoCardlessCallbackDeniedException;
        }

        $connection = $finder(
            (string) Auth::id(),
            $token,
            [
                'requisition_id' => $requisition->id,
                'account_ids' => $requisition->accounts,
                'institution_id' => config('finance.gocardless.institution_id'),
            ],
        );

        SyncBankAccountsJob::dispatch($connection->id);

        return redirect()->route('finance');
    }

    /**
     * Trigger an immediate bank account sync for the authenticated user's GoCardless connection.
     *
     * @throws GoCardlessNotConnectedException
     */
    public function syncNow(): RedirectResponse
    {
        $connection = IntegrationConnection::query()
            ->where('user_id', Auth::id())
            ->where('provider', IntegrationProvider::GoCardless)
            ->first();

        if ($connection === null) {
            throw new GoCardlessNotConnectedException;
        }

        SyncBankAccountsJob::dispatch($connection->id);

        return redirect()->route('finance');
    }
}
