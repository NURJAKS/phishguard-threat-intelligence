<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Neo4j Service
 * Integrates Neo4j for graph-based data relationships
 */
class Neo4jService
{
    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct()
    {
        $this->baseUrl = env('NEO4J_URL', 'http://localhost:7474');
        $this->username = env('NEO4J_USERNAME', 'neo4j');
        $this->password = env('NEO4J_PASSWORD', 'Admin@123');
    }

    /**
     * Execute Cypher query
     */
    private function executeQuery(string $query, array $params = []): array
    {
        try {
            // Neo4j HTTP API format
            $statements = [
                [
                    'statement' => $query,
                    'parameters' => $params
                ]
            ];

            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/db/neo4j/tx/commit", [
                    'statements' => $statements
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['results'][0]['data'])) {
                    return $data['results'][0]['data'];
                }
            }

            return [];
        } catch (\Exception $e) {
            Log::error("Neo4j query failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create or update phone node
     */
    public function createPhoneNode(array $phoneData): bool
    {
        try {
            $phone = $phoneData['phone'] ?? null;
            if (!$phone) {
                return false;
            }

            $query = "
                MERGE (p:Phone {phone: \$phone})
                SET p.phone_e164 = \$phone_e164,
                    p.country = \$country,
                    p.region = \$region,
                    p.city = \$city,
                    p.operator = \$operator,
                    p.phone_type = \$phone_type,
                    p.risk_score = \$risk_score,
                    p.verdict = \$verdict,
                    p.updated_at = datetime()
                RETURN p
            ";

            $params = [
                'phone' => $phone,
                'phone_e164' => $phoneData['international_format'] ?? $phone,
                'country' => $phoneData['country'] ?? null,
                'region' => $phoneData['region'] ?? null,
                'city' => $phoneData['city'] ?? null,
                'operator' => $phoneData['operator_name'] ?? null,
                'phone_type' => $phoneData['phone_type'] ?? null,
                'risk_score' => $phoneData['risk_score'] ?? 0,
                'verdict' => $phoneData['verdict'] ?? 'Unknown',
            ];

            $result = $this->executeQuery($query, $params);
            return !empty($result);
        } catch (\Exception $e) {
            Log::error("Neo4j create phone node failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create relationship between two phones
     */
    public function createRelationship(string $phone1, string $phone2, string $relationshipType = 'CONNECTED_TO'): bool
    {
        try {
            $query = "
                MATCH (p1:Phone {phone: \$phone1})
                MATCH (p2:Phone {phone: \$phone2})
                MERGE (p1)-[r:{$relationshipType}]->(p2)
                SET r.created_at = datetime()
                RETURN r
            ";

            $result = $this->executeQuery($query, [
                'phone1' => $phone1,
                'phone2' => $phone2
            ]);

            return !empty($result);
        } catch (\Exception $e) {
            Log::error("Neo4j create relationship failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find related phones
     */
    public function findRelatedPhones(string $phone, int $depth = 2): array
    {
        try {
            $query = "
                MATCH (p:Phone {phone: \$phone})-[*1..{$depth}]-(related:Phone)
                RETURN DISTINCT related.phone as phone, related.operator as operator, related.risk_score as risk_score
                LIMIT 50
            ";

            $result = $this->executeQuery($query, ['phone' => $phone]);
            
            $phones = [];
            foreach ($result as $row) {
                if (isset($row['row']) && is_array($row['row']) && count($row['row']) > 0) {
                    $phones[] = [
                        'phone' => $row['row'][0] ?? null,
                        'operator' => $row['row'][1] ?? null,
                        'risk_score' => $row['row'][2] ?? 0,
                    ];
                }
            }

            return $phones;
        } catch (\Exception $e) {
            Log::error("Neo4j find related phones failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get phone connections graph
     */
    public function getPhoneGraph(string $phone): array
    {
        try {
            $query = "
                MATCH path = (p:Phone {phone: \$phone})-[*1..2]-(connected:Phone)
                RETURN path
                LIMIT 100
            ";

            $result = $this->executeQuery($query, ['phone' => $phone]);
            return $result;
        } catch (\Exception $e) {
            Log::error("Neo4j get phone graph failed: " . $e->getMessage());
            return [];
        }
    }
}

