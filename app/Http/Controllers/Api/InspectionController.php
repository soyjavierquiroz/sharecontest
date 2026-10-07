<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Submission;
use App\Services\ApiInspectionPresenter;
use App\Services\ApiRuleValidator;
use App\Services\RefreshSubmission;
use App\Services\Social\PlatformDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InspectionController extends Controller
{
    public function store(
        Request $request,
        PlatformDetector $detector,
        RefreshSubmission $refresh,
        ApiRuleValidator $rules,
        ApiInspectionPresenter $presenter,
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'url' => ['required', 'string', 'max:2048'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'campaign_reference' => ['nullable', 'string', 'max:255'],
            'rules' => ['nullable', 'array'],
            'rules.allowed_platforms' => ['sometimes', 'array'],
            'rules.allowed_platforms.*' => ['string', 'in:tiktok,instagram,facebook'],
            'rules.required_hashtags' => ['sometimes', 'array'],
            'rules.required_hashtags.*' => ['string', 'max:255'],
            'rules.required_mentions' => ['sometimes', 'array'],
            'rules.required_mentions.*' => ['string', 'max:255'],
            'rules.published_from' => ['sometimes', 'date_format:Y-m-d'],
            'rules.published_until' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:rules.published_from'],
        ]);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->toArray());
        }
        $input = $validator->validated();

        try {
            $platform = $detector->detect($input['url']);
            $canonicalUrl = $detector->normalize($input['url']);
            $externalId = $detector->externalId($input['url']);
        } catch (InvalidArgumentException $e) {
            return $this->invalid(['url' => [$e->getMessage()]]);
        }

        $externalReference = $this->nullableString($input['external_reference'] ?? null);
        $campaignReference = $this->nullableString($input['campaign_reference'] ?? null);
        $submission = DB::transaction(function () use ($externalReference, $campaignReference, $platform, $input, $canonicalUrl, $externalId) {
            $contest = Contest::query()->firstOrCreate(
                ['slug' => 'api-inspections'],
                ['name' => 'API inspections', 'required_hashtag' => '', 'is_active' => false],
            );
            $submission = $externalReference === null ? null : Submission::query()
                ->where('source', 'api')
                ->where('external_reference', $externalReference)
                ->lockForUpdate()
                ->first();

            if ($submission === null) {
                $submission = Submission::create([
                    'contest_id' => $contest->id,
                    'source' => 'api',
                    'external_reference' => $externalReference,
                    'campaign_reference' => $campaignReference,
                    'platform' => $platform,
                    'original_url' => $input['url'],
                    'canonical_url' => $canonicalUrl,
                    'external_id' => $externalId,
                    'status' => 'pending',
                ]);
            } else {
                $submission->fill([
                    'campaign_reference' => $campaignReference ?? $submission->campaign_reference,
                    'platform' => $platform,
                    'original_url' => $input['url'],
                    'canonical_url' => $canonicalUrl,
                    'external_id' => $externalId,
                ])->save();
            }

            return $submission;
        });

        $submission = $refresh->handle($submission);
        $validation = $rules->validate($submission, $input['rules'] ?? null);

        return response()->json([
            'success' => true,
            'request_id' => (string) Str::uuid(),
            'external_reference' => $submission->external_reference,
            'campaign_reference' => $submission->campaign_reference,
            'data' => $presenter->present($submission),
            'validation' => $validation,
        ]);
    }

    /** @param array<string, array<int, string>> $fields */
    private function invalid(array $fields): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INVALID_REQUEST',
                'message' => 'The request is invalid.',
                'fields' => $fields,
            ],
        ], 422);
    }

    private function nullableString(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
