# 🛍️ Product Catalog API

A production-ready REST API built with **Laravel 10+** for managing a product catalog with full-text search, caching and cloud storage.

## ✨ Features

- **CRUD completo** de produtos com soft delete
- **Full-text search** via Elasticsearch 8 (fuzzy, multi-field, filtros combinados)
- **Cache Redis** com TTL configurável, invalidação automática e skip para páginas altas
- **Upload de imagens** para S3 (simulado com LocalStack em desenvolvimento)
- **Sincronização assíncrona** ES via Queue (Redis) + Observer pattern
- **Arquitetura limpa**: Controller → Service → Repository → DTO
- **Tratamento de erros** padronizado em JSON
- **Testes** unitários e de feature com Pest

---

## 🏗️ Stack

| Camada | Tecnologia |
|--------|-----------|
| Framework | Laravel 10 + PHP 8.2 |
| Banco de dados | MySQL 8 |
| Busca | Elasticsearch 8.11 |
| Cache / Queue | Redis 7 |
| Storage | AWS S3 (LocalStack em dev) |
| Containers | Docker + Docker Compose |
| Testes | Pest PHP 2 |
| CI/CD | GitHub Actions |

---

## 🚀 Início Rápido (Docker)

### Pré-requisitos

- Docker Engine ≥ 24
- Docker Compose ≥ 2.20

### 1. Clone e configure

```bash
git clone https://github.com/SEU_USUARIO/product-catalog-api.git
cd product-catalog-api

cp .env.example .env
```

### 2. Suba os containers

```bash
docker compose up -d
```

> ⏳ Na primeira vez, o Elasticsearch leva ~30s para inicializar. Aguarde o healthcheck verde antes de prosseguir.

### 3. Instale as dependências

```bash
docker compose exec app composer install
```

### 4. Configure a aplicação

```bash
# Gerar APP_KEY
docker compose exec app php artisan key:generate

# Executar migrations
docker compose exec app php artisan migrate

# Popular com dados de exemplo (50 produtos)
docker compose exec app php artisan db:seed

# Indexar produtos no Elasticsearch
docker compose exec app php artisan elastic:reindex
```

### 5. Iniciar o worker de filas (terminal separado)

```bash
docker compose exec app php artisan queue:work --tries=3
```

### 6. Acesse a API

```
http://localhost:8000/api/v1
```

---

## 📋 Endpoints

### Produtos

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/api/v1/products` | Lista paginada com filtros |
| `POST` | `/api/v1/products` | Cria produto |
| `GET` | `/api/v1/products/{id}` | Busca por ID (cache Redis) |
| `PUT` | `/api/v1/products/{id}` | Atualiza produto |
| `DELETE` | `/api/v1/products/{id}` | Soft delete |
| `POST` | `/api/v1/products/{id}/image` | Upload imagem (S3) |

### Busca

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/api/v1/search/products` | Busca full-text via Elasticsearch |

### Outros

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/api/health` | Health check |

---

## 🔍 Parâmetros de Busca

### `GET /api/v1/products`

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `status` | string | `active` ou `inactive` |
| `category` | string | Filtro por categoria |
| `min_price` | number | Preço mínimo |
| `max_price` | number | Preço máximo |
| `sort` | string | `price` ou `created_at` |
| `order` | string | `asc` ou `desc` |
| `page` | int | Página (padrão: 1) |
| `per_page` | int | Itens por página (padrão: 15) |

### `GET /api/v1/search/products`

Todos os parâmetros acima, mais:

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `q` | string | Busca em `name` e `description` (fuzzy) |

---

## 📦 Exemplos de Uso

### Criar produto

```bash
curl -X POST http://localhost:8000/api/v1/products \
  -H "Content-Type: application/json" \
  -d '{
    "sku": "LAPTOP-001",
    "name": "MacBook Pro 16",
    "description": "Apple M3 Pro chip, 18GB RAM",
    "price": 2499.99,
    "category": "Electronics",
    "status": "active"
  }'
```

### Buscar por texto + filtros

```bash
curl "http://localhost:8000/api/v1/search/products?q=laptop&category=Electronics&min_price=1000&sort=price&order=asc"
```

### Upload de imagem

```bash
curl -X POST http://localhost:8000/api/v1/products/1/image \
  -F "image=@/path/to/product.jpg"
```

---

## 🗂️ Estrutura do Projeto

```
app/
├── Console/Commands/
│   └── ElasticReindex.php       # php artisan elastic:reindex
├── Domain/Product/
│   ├── DTOs/
│   │   └── ProductDTO.php       # Objeto de dados imutável e tipado
│   ├── Jobs/
│   │   └── SyncProductToElastic.php  # Job assíncrono (queue Redis)
│   ├── Observers/
│   │   └── ProductObserver.php  # Auto-sync ao criar/atualizar/deletar
│   ├── Repositories/
│   │   └── ProductRepository.php # Acesso ao banco (Eloquent)
│   └── Services/
│       ├── CacheService.php      # Redis cache com TTL e invalidação
│       ├── ElasticSearchService.php # Index, busca e sync ES
│       └── ProductService.php    # Orquestra regras de negócio
├── Exceptions/
│   └── Handler.php              # Respostas JSON padronizadas
├── Http/
│   ├── Controllers/Api/
│   │   └── ProductController.php
│   ├── Requests/Product/        # FormRequests com validação
│   └── Resources/
│       └── ProductResource.php  # Transformação de saída
└── Models/
    └── Product.php              # Eloquent + SoftDeletes
```

---

## 🧪 Testes

Os testes usam **SQLite em memória** para velocidade e isolamento. Elasticsearch e Cache são mockados nos feature tests. O runtime usa MySQL e Redis reais.

```bash
# Todos os testes
docker compose exec app ./vendor/bin/pest

# Apenas unit tests
docker compose exec app ./vendor/bin/pest --testsuite=Unit

# Apenas feature tests
docker compose exec app ./vendor/bin/pest --testsuite=Feature

# Com cobertura
docker compose exec app ./vendor/bin/pest --coverage
```

### Cobertura

| Suite | Testes |
|-------|--------|
| Unit | ProductDTO, CacheService (keys, skip logic) |
| Feature | CRUD completo, validações, soft delete, search filters, cache invalidation |

---

## ⚙️ Configurações Importantes

### Cache (`.env`)

```env
CACHE_TTL=120        # TTL em segundos (padrão: 120s)
CACHE_DRIVER=redis
```

Cache é aplicado em:
- `GET /products/{id}`: chave `product:{id}`
- `GET /search/products`: chave `search:products:{md5(params)}`

Invalidado automaticamente em `update` e `delete`.
**Skipado** quando `page > 50` para evitar pressão de memória.

### S3 / LocalStack

Em desenvolvimento, o LocalStack simula o S3. O bucket `products-bucket` é criado automaticamente pelo script `docker/localstack/init-aws.sh`.

Para inspecionar os arquivos:

```bash
docker compose exec localstack awslocal s3 ls s3://products-bucket/
```

### Reindexar Elasticsearch

```bash
# Reindexar sem apagar o índice
docker compose exec app php artisan elastic:reindex

# Recriar o índice do zero
docker compose exec app php artisan elastic:reindex --fresh
```

---

## 🔧 Comandos Úteis

```bash
# Ver logs da aplicação
docker compose logs app -f

# Limpar cache Redis
docker compose exec app php artisan cache:clear

# Rodar worker de filas
docker compose exec app php artisan queue:work

# Verificar jobs pendentes
docker compose exec redis redis-cli llen product-catalog-api_database_default

# Lint com Pint
docker compose exec app ./vendor/bin/pint

# Verificar lint sem aplicar
docker compose exec app ./vendor/bin/pint --test
```

---

## 🏛️ Decisões Técnicas

| Decisão | Motivo |
|---------|--------|
| **DTO imutável com `readonly`** | Previne mutação acidental entre camadas; tipo explícito no método |
| **Observer + Job (Queue)** | Desacopla o CRUD da sync com ES; resiliente a falhas do ES |
| **Soft delete** | Preserva histórico para auditoria; fácil de restaurar |
| **SQLite em testes** | Zero dependência de serviços externos na CI; testes rápidos |
| **Mocks de ES e Cache em feature tests** | Testa lógica de negócio isolada; ES testado em unit tests próprios |
| **Skip cache em page > 50** | Evita ocupar memória Redis com resultados raramente acessados |
| **LocalStack** | Simula S3/SQS sem custo AWS; mesma interface do SDK |
| **Fuzzy matching no ES** | Tolerância a erros de digitação na busca |

---

## ⚠️ Limitações Conhecidas

- Upload de imagem requer LocalStack rodando e bucket criado
- Elasticsearch pode levar 30-60s para inicializar no primeiro `docker compose up`
- `CacheService::invalidateSearch` usa Redis SCAN. Em clusters Redis, pode precisar de ajuste
- Não há autenticação implementada (pode ser adicionado com Laravel Sanctum)

## 🔮 Próximos Passos

- [ ] Autenticação via Laravel Sanctum (API tokens)
- [ ] Rate limiting por IP/token
- [ ] Endpoint de restauração de produtos deletados
- [ ] Dashboard com Kibana para visualizar o índice ES
- [ ] Webhook via SQS ao criar produto
- [ ] Versionamento da API (v2)
- [ ] Documentação OpenAPI/Swagger automática

---

## 📄 Licença

MIT
