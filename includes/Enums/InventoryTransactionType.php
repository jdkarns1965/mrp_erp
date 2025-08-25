<?php
declare(strict_types=1);

namespace MRP\Enums;

/**
 * Inventory Transaction Type Enum - PHP 8.2 Feature
 * Type-safe transaction types for inventory movements
 */
enum InventoryTransactionType: string
{
    case RECEIPT = 'receipt';
    case ISSUE = 'issue';
    case ADJUSTMENT = 'adjustment';
    case TRANSFER = 'transfer';
    case RETURN = 'return';
    case SCRAP = 'scrap';
    case PRODUCTION_CONSUMPTION = 'production_consumption';
    case PRODUCTION_OUTPUT = 'production_output';
    
    public function label(): string
    {
        return match($this) {
            self::RECEIPT => 'Receipt',
            self::ISSUE => 'Issue',
            self::ADJUSTMENT => 'Adjustment',
            self::TRANSFER => 'Transfer',
            self::RETURN => 'Return',
            self::SCRAP => 'Scrap',
            self::PRODUCTION_CONSUMPTION => 'Production Consumption',
            self::PRODUCTION_OUTPUT => 'Production Output',
        };
    }
    
    public function isAdditive(): bool
    {
        return match($this) {
            self::RECEIPT, self::RETURN, self::PRODUCTION_OUTPUT => true,
            self::ISSUE, self::SCRAP, self::PRODUCTION_CONSUMPTION => false,
            self::ADJUSTMENT, self::TRANSFER => false, // Depends on sign
        };
    }
    
    public function requiresApproval(): bool
    {
        return match($this) {
            self::ADJUSTMENT, self::SCRAP => true,
            default => false,
        };
    }
    
    public function icon(): string
    {
        return match($this) {
            self::RECEIPT => '📥',
            self::ISSUE => '📤',
            self::ADJUSTMENT => '⚖️',
            self::TRANSFER => '🔄',
            self::RETURN => '↩️',
            self::SCRAP => '🗑️',
            self::PRODUCTION_CONSUMPTION => '⚙️',
            self::PRODUCTION_OUTPUT => '📦',
        };
    }
    
    public function getMultiplier(): int
    {
        return match($this) {
            self::RECEIPT, self::RETURN, self::PRODUCTION_OUTPUT => 1,
            self::ISSUE, self::SCRAP, self::PRODUCTION_CONSUMPTION => -1,
            default => 0, // Neutral, sign determined by quantity
        };
    }
}