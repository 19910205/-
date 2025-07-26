<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商品属性模型
 * 用于管理商品的属性配置
 * 
 * @author Augment Agent
 */
class GoodsAttribute extends BaseModel
{
    use HasFactory;

    protected $table = 'goods_attributes';

    // 属性类型常量
    const TYPE_TEXT = 1;    // 文本
    const TYPE_COLOR = 2;   // 颜色
    const TYPE_IMAGE = 3;   // 图片
    const TYPE_SIZE = 4;    // 尺寸
    const TYPE_NUMBER = 5;  // 数字

    // 输入类型常量
    const INPUT_TYPE_RADIO = 1;     // 单选
    const INPUT_TYPE_CHECKBOX = 2;  // 多选
    const INPUT_TYPE_INPUT = 3;     // 输入框
    const INPUT_TYPE_SELECT = 4;    // 下拉框

    protected $fillable = [
        'goods_id',
        'name',
        'values',
        'type',
        'input_type',
        'sort',
        'is_required',
        'is_filterable',
        'is_searchable',
        'unit',
        'default_value',
        'description',
        'validation_rules'
    ];

    protected $casts = [
        'values' => 'array',
        'validation_rules' => 'array',
        'is_required' => 'boolean',
        'is_filterable' => 'boolean',
        'is_searchable' => 'boolean'
    ];

    /**
     * 获取属性类型映射
     */
    public static function getTypeMap(): array
    {
        return [
            self::TYPE_TEXT => '文本',
            self::TYPE_COLOR => '颜色',
            self::TYPE_IMAGE => '图片',
            self::TYPE_SIZE => '尺寸',
            self::TYPE_NUMBER => '数字'
        ];
    }

    /**
     * 获取输入类型映射
     */
    public static function getInputTypeMap(): array
    {
        return [
            self::INPUT_TYPE_RADIO => '单选',
            self::INPUT_TYPE_CHECKBOX => '多选',
            self::INPUT_TYPE_INPUT => '输入框',
            self::INPUT_TYPE_SELECT => '下拉框'
        ];
    }

    /**
     * 关联商品
     */
    public function goods(): BelongsTo
    {
        return $this->belongsTo(Goods::class);
    }

    /**
     * 获取类型文本
     */
    public function getTypeTextAttribute(): string
    {
        return self::getTypeMap()[$this->type] ?? '';
    }

    /**
     * 获取输入类型文本
     */
    public function getInputTypeTextAttribute(): string
    {
        return self::getInputTypeMap()[$this->input_type] ?? '';
    }

    /**
     * 是否必选
     */
    public function isRequired(): bool
    {
        return $this->is_required;
    }

    /**
     * 是否可筛选
     */
    public function isFilterable(): bool
    {
        return $this->is_filterable;
    }

    /**
     * 是否可搜索
     */
    public function isSearchable(): bool
    {
        return $this->is_searchable;
    }

    /**
     * 获取属性值列表
     */
    public function getValuesList(): array
    {
        return $this->values ?? [];
    }

    /**
     * 添加属性值
     */
    public function addValue(string $value): bool
    {
        $values = $this->values ?? [];
        if (!in_array($value, $values)) {
            $values[] = $value;
            return $this->update(['values' => $values]);
        }
        return true;
    }

    /**
     * 移除属性值
     */
    public function removeValue(string $value): bool
    {
        $values = $this->values ?? [];
        $key = array_search($value, $values);
        if ($key !== false) {
            unset($values[$key]);
            return $this->update(['values' => array_values($values)]);
        }
        return true;
    }

    /**
     * 验证属性值
     */
    public function validateValue($value): bool
    {
        // 必选验证
        if ($this->is_required && empty($value)) {
            return false;
        }

        // 类型验证
        switch ($this->type) {
            case self::TYPE_NUMBER:
                return is_numeric($value);
            case self::TYPE_COLOR:
                return preg_match('/^#[0-9A-Fa-f]{6}$/', $value);
            case self::TYPE_IMAGE:
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            default:
                return true;
        }
    }

    /**
     * 获取验证规则
     */
    public function getValidationRules(): array
    {
        $rules = [];

        if ($this->is_required) {
            $rules[] = 'required';
        }

        switch ($this->type) {
            case self::TYPE_NUMBER:
                $rules[] = 'numeric';
                break;
            case self::TYPE_COLOR:
                $rules[] = 'regex:/^#[0-9A-Fa-f]{6}$/';
                break;
            case self::TYPE_IMAGE:
                $rules[] = 'url';
                break;
        }

        // 自定义验证规则
        if ($this->validation_rules) {
            $rules = array_merge($rules, $this->validation_rules);
        }

        return $rules;
    }

    /**
     * 格式化显示值
     */
    public function formatValue($value): string
    {
        switch ($this->type) {
            case self::TYPE_COLOR:
                return '<span style="background-color: ' . $value . '; width: 20px; height: 20px; display: inline-block; border: 1px solid #ccc;"></span> ' . $value;
            case self::TYPE_IMAGE:
                return '<img src="' . $value . '" style="width: 50px; height: 50px; object-fit: cover;" alt="' . $this->name . '">';
            case self::TYPE_NUMBER:
                return $value . ($this->unit ? ' ' . $this->unit : '');
            default:
                return $value;
        }
    }

    /**
     * 获取默认值
     */
    public function getDefaultValue()
    {
        return $this->default_value;
    }

    /**
     * 设置默认值
     */
    public function setDefaultValue($value): bool
    {
        return $this->update(['default_value' => $value]);
    }

    /**
     * 获取属性描述
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * 获取单位
     */
    public function getUnit(): ?string
    {
        return $this->unit;
    }
}
