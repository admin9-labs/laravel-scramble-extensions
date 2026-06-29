# Laravel Scramble Extensions

面向 Mitoop 生态业务接口约定的 [Scramble](https://scramble.dedoc.co/) (OpenAPI 文档生成器) 适配包。

> 这个包不是通用 Scramble 扩展。它服务于使用 `mitoop/laravel-api-response`、`mitoop/laravel-efficient-form-request`、`mitoop/laravel-query-builder` 的项目，把这些业务约定补充到 Scramble 生成的 OpenAPI 文档中。

## 兼容性

| Package | Version |
| --- | --- |
| PHP | `^8.3` |
| Laravel | `^13.0` |
| Scramble | `^0.13.30` |

旧版 Laravel/Scramble 项目请继续使用本包 `v0.2.x`。

主要功能：

- 自动将 200 响应包装为统一的业务信封结构 `{success, code, message, data, [meta], request_id}`
- 支持场景化表单请求 (Scene FormRequest) 的参数提取
- 支持 Filter 查询参数自动提取（筛选字段、排序、分页）
- 支持读取数据库字段注释并写入 OpenAPI schema description

各功能模块均可通过配置文件独立开启或关闭；未安装对应 Mitoop 包时，对应模块会自动跳过。

## 安装

```bash
composer require admin9/laravel-scramble-extensions
```

发布配置文件：

```bash
php artisan vendor:publish --tag=scramble-extensions-config
```

### 可选依赖

根据你使用的 Mitoop 约定，按需安装对应的包：

```bash
# 业务响应信封包装
composer require mitoop/laravel-api-response

# 场景化表单请求
composer require mitoop/laravel-efficient-form-request

# Filter 查询参数提取
composer require mitoop/laravel-query-builder
```

## 配置

配置文件 `config/scramble-extensions.php`：

```php
return [
    'response' => [
        'enabled' => true,
        // 控制器需要 use 的 trait，提供 $this->success() 等方法
        'trait' => 'Mitoop\\Http\\RespondsWithJson',
        // 模型命名空间，用于分页响应自动推断模型
        'model_namespace' => 'App\\Models',
        // 从数据库字段注释生成 schema description
        'column_comments' => true,
    ],

    'scene_form_request' => [
        'enabled' => true,
    ],

    'filter' => [
        'enabled' => true,
        'pagination' => [
            'page_size_default' => 15,
            'page_size_max' => 100,
        ],
    ],
];
```

## 功能说明

### 1. 业务响应信封包装 (Response)

自动将所有 200 响应包装为统一的业务信封结构。

控制器需要 use 配置中指定的 Mitoop trait（默认 `Mitoop\Http\RespondsWithJson`），通过 `$this->success()`、`$this->error()`、`$this->deny()` 返回响应。

```php
class UserController extends Controller
{
    use \Mitoop\Http\RespondsWithJson;

    public function show(User $user)
    {
        return $this->success($user);
    }

    public function index()
    {
        return $this->success(User::paginate());
    }
}
```

生成的 OpenAPI 文档中，标准响应结构为：

```json
{
  "success": true,
  "code": 0,
  "message": "",
  "data": { ... },
  "request_id": "uuid7"
}
```

分页响应会额外包含 `meta` 字段：

```json
{
  "success": true,
  "code": 0,
  "message": "",
  "data": [ ... ],
  "meta": {
    "pagination": "length_aware",
    "page": 1,
    "page_size": 15,
    "has_more": true,
    "total": 100
  },
  "request_id": "uuid7"
}
```

### 2. 场景化表单请求提取 (Scene FormRequest)

支持 `mitoop/laravel-efficient-form-request`，允许同一个 FormRequest 根据控制器方法名定义不同的验证规则。

扩展会自动查找 FormRequest 上的 `{actionName}Rules()` 方法并提取参数到 OpenAPI 文档。

```php
use Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest;

class UserRequest extends EfficientSceneFormRequest
{
    // store 方法使用的规则
    public function storeRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ];
    }

    // update 方法使用的规则
    public function updateRules(): array
    {
        return [
            'name' => 'string|max:255',
        ];
    }
}
```

```php
class UserController extends Controller
{
    public function store(UserRequest $request) { ... }
    public function update(UserRequest $request, User $user) { ... }
}
```

`store` 接口文档会包含 `name`（必填）和 `email`（必填）参数，`update` 接口文档只包含 `name` 参数。

### 3. Filter 查询参数提取 (Filter)

支持 `mitoop/laravel-query-builder`，自动从 Filter 类中提取筛选字段、排序选项和分页参数到 OpenAPI 文档。

```php
use Mitoop\LaravelQueryBuilder\AbstractFilter;

class UserFilter extends AbstractFilter
{
    protected array $allowedSorts = ['created_at', 'name'];

    public function rules(): array
    {
        return [
            'name',
            'email',
            'status',
        ];
    }
}
```

```php
class UserController extends Controller
{
    public function index()
    {
        $users = User::filter(UserFilter::class)->paginate();

        return $this->success($users);
    }
}
```

生成的文档会自动包含以下 query 参数：

- `name`、`email`、`status` — 筛选字段
- `sort` — 排序字段，可选值 `created_at`、`name`，前缀 `-` 表示降序
- `page_size` — 每页条数（默认 15，最大 100，可通过配置修改）
- `page` — 页码

### 4. 模型字段注释 (Column Comments)

当模型字段在数据库 schema 中带有 comment 时，扩展会把 comment 写入对应 OpenAPI property 的 `description`。这用于让接口字段说明跟迁移/数据库字段注释保持一致。

该能力依赖数据库驱动能通过 Laravel schema builder 返回 `comment` 字段；不支持字段注释的驱动会安全跳过。

## License

MIT
