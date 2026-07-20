#!/bin/bash
# Setup Elasticsearch index, synonym set, and initial configuration.
set -e

ES_HOST="${ELASTICSEARCH_HOST:-localhost}"
ES_PORT="${ELASTICSEARCH_PORT:-9200}"
ES_URL="http://${ES_HOST}:${ES_PORT}"

COMPANY_ID="${COMPANY_ID:-default}"
PRODUCTS_INDEX="${COMPANY_ID}_products"
SYNONYM_SET="${COMPANY_ID}_synonyms"

echo "Waiting for Elasticsearch to be ready..."
until curl -s "${ES_URL}/_cluster/health" | grep -qE '"status":"(green|yellow)"'; do
    sleep 2
done
echo "Elasticsearch is ready."

# Create the synonym set FIRST — the products index references it and will fail
# to create if it does not exist. Seed a harmless placeholder rule (ES rejects an
# empty set); real synonyms are pushed from the DB via the admin UI / SynonymService.
echo "Creating synonym set: ${SYNONYM_SET}..."
curl -s -X PUT "${ES_URL}/_synonyms/${SYNONYM_SET}" \
    -H 'Content-Type: application/json' \
    -d '{"synonyms_set":[{"synonyms":"__nosynonym_a__, __nosynonym_b__"}]}'
echo ""

# Create products index (substitute the synonym-set placeholder in the mapping template)
echo "Creating products index: ${PRODUCTS_INDEX}..."
curl -s -X DELETE "${ES_URL}/${PRODUCTS_INDEX}" > /dev/null 2>&1 || true
sed "s/__SYNONYM_SET__/${SYNONYM_SET}/g" elasticsearch/mappings/products.json \
    | curl -s -X PUT "${ES_URL}/${PRODUCTS_INDEX}" \
        -H 'Content-Type: application/json' \
        --data-binary @-
echo ""

# Create search_analytics index
echo "Creating search_analytics index..."
curl -s -X DELETE "${ES_URL}/search_analytics" > /dev/null 2>&1 || true
curl -s -X PUT "${ES_URL}/search_analytics" \
    -H 'Content-Type: application/json' \
    -d @elasticsearch/mappings/search_analytics.json
echo ""

echo "Elasticsearch setup complete."
