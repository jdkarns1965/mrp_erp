<?php
declare(strict_types=1);

namespace MRP\Enums;

/**
 * Production Status Enum - PHP 8.2 Feature
 * Type-safe status management for production orders and operations
 */
enum ProductionStatus: string
{
    case PLANNED = 'planned';
    case RELEASED = 'released';
    case IN_PROGRESS = 'in_progress';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    
    public function label(): string
    {
        return match($this) {
            self::PLANNED => 'Planned',
            self::RELEASED => 'Released',
            self::IN_PROGRESS => 'In Progress',
            self::ON_HOLD => 'On Hold',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }
    
    public function color(): string
    {
        return match($this) {
            self::PLANNED => 'blue',
            self::RELEASED => 'cyan',
            self::IN_PROGRESS => 'orange',
            self::ON_HOLD => 'yellow',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
        };
    }
    
    public function getBadgeClass(): string
    {
        return match($this) {
            self::PLANNED => 'badge-info',
            self::RELEASED => 'badge-primary',
            self::IN_PROGRESS => 'badge-warning',
            self::ON_HOLD => 'badge-secondary',
            self::COMPLETED => 'badge-success',
            self::CANCELLED => 'badge-danger',
        };
    }
    
    public function isActive(): bool
    {
        return match($this) {
            self::PLANNED, self::RELEASED, self::IN_PROGRESS => true,
            default => false,
        };
    }
    
    public function canStart(): bool
    {
        return $this === self::RELEASED || $this === self::PLANNED;
    }
    
    public function canComplete(): bool
    {
        return $this === self::IN_PROGRESS;
    }
    
    public function getPriority(): int
    {
        return match($this) {
            self::IN_PROGRESS => 1,
            self::RELEASED => 2,
            self::PLANNED => 3,
            self::ON_HOLD => 4,
            self::COMPLETED => 5,
            self::CANCELLED => 6,
        };
    }
}