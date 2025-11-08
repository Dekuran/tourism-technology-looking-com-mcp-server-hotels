<?php

namespace App\Mcp\TourismServer\Tools\Discovery;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\JsonSchema\JsonSchema;
use App\Services\TourismService;

class RecommendAttractionsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Get personalized attraction recommendations based on traveler preferences and profile.
        
        This smart discovery tool uses AI-powered matching to recommend attractions that align with:
        - User interests and preferences (art, history, nature, adventure, etc.)
        - Travel type (solo, family, romantic, business, adventure)
        - Age group considerations
        - Past visit history
        
        The tool ranks attractions by relevance score and provides detailed matches.
        
        Use this tool when users ask:
        - "What should I visit based on my interests?"
        - "I love art and history, what do you recommend?"
        - "What's good for families in Vienna?"
        - "I'm traveling solo, what attractions would I enjoy?"
    MARKDOWN;

    public function __construct(
        protected TourismService $tourismService
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'destination_name' => 'nullable|string',
            'destination_id' => 'nullable|integer',
            'preferences' => 'nullable|array',
            'travel_type' => 'nullable|string',
            'age_group' => 'nullable|string',
            'budget' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        Log::info('Recommending attractions:', $validated);

        // Get destination
        $destination = null;
        if (isset($validated['destination_id'])) {
            $destination = $this->tourismService->getDestination($validated['destination_id']);
        } elseif (isset($validated['destination_name'])) {
            $destination = $this->tourismService->getDestinationByName($validated['destination_name']);
        }

        if (!$destination) {
            return Response::text("Destination not found. Please provide a valid destination name or ID.");
        }

        // Get or create user profile
        $userId = $validated['user_id'] ?? 'GUEST-' . substr(md5(uniqid()), 0, 8);
        $userProfile = [
            'user_id' => $userId,
            'preferences' => $validated['preferences'] ?? [],
            'travel_type' => $validated['travel_type'] ?? 'general',
            'age_group' => $validated['age_group'] ?? 'adult',
            'budget' => $validated['budget'] ?? 'moderate',
        ];

        // Save/update user profile
        $this->tourismService->saveUserProfile($userProfile);

        // Get personalized recommendations
        $limit = $validated['limit'] ?? 6;
        $recommendations = $this->tourismService->getRecommendedAttractions(
            destinationId: $destination['id'],
            userProfile: $userProfile,
            limit: $limit
        );

        if (empty($recommendations)) {
            return Response::text("No attractions found for {$destination['name']} matching your preferences.");
        }

        // Build response
        $response = "# 🎯 Personalized Recommendations for {$destination['name']}\n\n";
        
        // Show user profile summary
        if (!empty($userProfile['preferences'])) {
            $response .= "**Your Interests:** " . implode(', ', array_map('ucfirst', $userProfile['preferences'])) . "\n";
        }
        if ($userProfile['travel_type'] !== 'general') {
            $response .= "**Travel Type:** " . ucfirst($userProfile['travel_type']) . "\n";
        }
        if ($userProfile['age_group'] !== 'adult') {
            $response .= "**Age Group:** " . ucfirst($userProfile['age_group']) . "\n";
        }
        $response .= "\n";

        $response .= "We've found **" . count($recommendations) . " attractions** perfect for you!\n\n";
        $response .= "---\n\n";

        foreach ($recommendations as $index => $attraction) {
            $rank = $index + 1;
            $matchScore = $attraction['match_score'];
            $matchBadge = $this->getMatchBadge($matchScore);
            
            $response .= "## {$rank}. {$attraction['name']} {$matchBadge}\n\n";
            $response .= "**Category:** {$attraction['category']}\n";
            $response .= "**Match Score:** {$matchScore}% - {$this->getMatchReason($matchScore)}\n";
            
            if (!empty($attraction['matched_tags'])) {
                $response .= "**Why you'll love it:** " . implode(', ', $attraction['matched_tags']) . "\n";
            }
            
            $response .= "**Description:** {$attraction['description']}\n";
            
            if ($attraction['bookable']) {
                $response .= "**Price:** {$attraction['price']} {$attraction['currency']} per ticket 🎫\n";
                $response .= "**Duration:** ~{$attraction['duration_minutes']} minutes\n";
                $response .= "**Opening Hours:** {$attraction['opening_hours']}\n";
            } else {
                $response .= "**Entry:** Free or on-site tickets 📍\n";
            }
            
            $response .= "**Attraction ID:** {$attraction['id']}\n";
            $response .= "\n";
        }

        $response .= "---\n\n";
        $response .= "💡 **Next Steps:**\n";
        $response .= "- Use `GetAttractionDetails(attraction_id)` to learn more about any attraction\n";
        $response .= "- Use `PrepareBooking(attraction_id, ...)` to book tickets for bookable attractions 🎫\n";

        return Response::text($response);
    }

    /**
     * Get match badge based on score
     */
    private function getMatchBadge(int $score): string
    {
        if ($score >= 90) return "🌟 Perfect Match";
        if ($score >= 75) return "⭐ Excellent Match";
        if ($score >= 60) return "✨ Good Match";
        return "👍 Recommended";
    }

    /**
     * Get match reason based on score
     */
    private function getMatchReason(int $score): string
    {
        if ($score >= 90) return "Exactly what you're looking for!";
        if ($score >= 75) return "Highly aligned with your interests";
        if ($score >= 60) return "Matches several of your preferences";
        return "Worth considering";
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->string()
                ->description('Optional user ID to track preferences across sessions (e.g., "USR-9132"). Auto-generated if not provided.'),

            'destination_name' => $schema->string()
                ->description('Name of the destination (e.g., "Vienna", "Salzburg"). Either this or destination_id is required.'),

            'destination_id' => $schema->integer()
                ->description('ID of the destination. Either this or destination_name is required.'),

            'preferences' => $schema->array()
                ->description('Array of user interests/preferences. Options: "history", "art", "architecture", "nature", "adventure", "culture", "music", "sports", "food", "family-friendly", "romantic", "religious".')
                ->items($schema->string()),

            'travel_type' => $schema->string()
                ->description('Type of travel. Options: "solo", "family", "romantic", "business", "adventure", "cultural", "budget", "luxury".'),

            'age_group' => $schema->string()
                ->description('Age group of traveler(s). Options: "child", "teen", "adult", "senior", "family".'),

            'budget' => $schema->string()
                ->description('Budget level. Options: "budget" (free/low-cost), "moderate", "luxury".'),

            'limit' => $schema->integer()
                ->description('Maximum number of recommendations to return (1-20). Defaults to 6.'),
        ];
    }
}

