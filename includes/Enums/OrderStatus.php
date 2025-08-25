<?php
declare(strict_types=1);

namespace MRP\Enums;

/**
 * Order Status Enum - PHP 8.2 Feature
 * Provides type-safe status management for customer orders
 */
enum OrderStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case IN_PRODUCTION = 'in_production';
    case COMPLETED = 'completed';
    case SHIPPED = 'shipped';
    case CANCELLED = 'cancelled';
    
    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
            self::IN_PRODUCTION => 'In Production',
            self::COMPLETED => 'Completed',
            self::SHIPPED => 'Shipped',
            self::CANCELLED => 'Cancelled',
        };
    }
    
    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'yellow',
            self::CONFIRMED => 'blue',
            self::IN_PRODUCTION => 'orange',
            self::COMPLETED => 'green',
            self::SHIPPED => 'purple',
            self::CANCELLED => 'red',
        };
    }
    
    public function icon(): string
    {
        return match($this) {
            self::DRAFT => '📝',
            self::PENDING => '⏳',
            self::CONFIRMED => '✅',
            self::IN_PRODUCTION => '🏭',
            self::COMPLETED => '✔️',
            self::SHIPPED => '📦',
            self::CANCELLED => '❌',
        };
    }
    
    public function canTransitionTo(self $newStatus): bool
    {
        return match($this) {
            self::DRAFT => in_array($newStatus, [self::PENDING, self::CANCELLED]),
            self::PENDING => in_array($newStatus, [self::CONFIRMED, self::CANCELLED]),
            self::CONFIRMED => in_array($newStatus, [self::IN_PRODUCTION, self::CANCELLED]),
            self::IN_PRODUCTION => in_array($newStatus, [self::COMPLETED, self::CANCELLED]),
            self::COMPLETED => $newStatus === self::SHIPPED,
            self::SHIPPED => false,
            self::CANCELLED => false,
        };
    }
    
    public static function getActiveStatuses(): array
    {
        return [
            self::PENDING,
            self::CONFIRMED,
            self::IN_PRODUCTION,
        ];
    }
}