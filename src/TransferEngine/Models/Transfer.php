<?php
declare(strict_types=1);

namespace TransferEngine\Models;

/**
 * Transfer Model - Represents a stock transfer between outlets
 */
class Transfer
{
    private ?int $id = null;
    private string $outletFrom;
    private string $outletTo;
    private string $transferDate;
    private string $transferNotes;
    private string $createdBy;
    private string $status;
    private array $products = [];
    private \DateTime $createdAt;
    
    public function __construct(array $data)
    {
        $this->outletFrom = $data['outlet_from'];
        $this->outletTo = $data['outlet_to'];
        $this->transferDate = $data['transfer_date'];
        $this->transferNotes = $data['transfer_notes'] ?? '';
        $this->createdBy = $data['created_by'] ?? '';
        $this->status = $data['status'] ?? 'pending';
        $this->createdAt = new \DateTime();
    }
    
    // Getters and setters
    public function getId(): ?int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }
    
    public function getOutletFrom(): string { return $this->outletFrom; }
    public function getOutletTo(): string { return $this->outletTo; }
    public function getTransferDate(): string { return $this->transferDate; }
    public function getTransferNotes(): string { return $this->transferNotes; }
    public function getCreatedBy(): string { return $this->createdBy; }
    public function getStatus(): string { return $this->status; }
    
    public function addProduct(ProductLine $product): void
    {
        $this->products[] = $product;
    }
    
    public function getProducts(): array
    {
        return $this->products;
    }
}

/**
 * Product Model - Represents a product in the system
 */
class Product
{
    private string $id;
    private string $name;
    private string $sku;
    private float $price;
    private string $category;
    private array $metadata;
    
    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->sku = $data['sku'] ?? '';
        $this->price = (float)($data['price'] ?? 0);
        $this->category = $data['category'] ?? '';
        $this->metadata = $data['metadata'] ?? [];
    }
    
    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSku(): string { return $this->sku; }
    public function getPrice(): float { return $this->price; }
    public function getCategory(): string { return $this->category; }
    public function getMetadata(): array { return $this->metadata; }
}

/**
 * Outlet Model - Represents a store or warehouse location
 */
class Outlet
{
    private string $id;
    private string $name;
    private bool $isWarehouse;
    private bool $isActive;
    private array $settings;
    
    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->isWarehouse = (bool)($data['is_warehouse'] ?? false);
        $this->isActive = (bool)($data['is_active'] ?? true);
        $this->settings = $data['settings'] ?? [];
    }
    
    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function isWarehouse(): bool { return $this->isWarehouse; }
    public function isActive(): bool { return $this->isActive; }
    public function getSettings(): array { return $this->settings; }
}

/**
 * Product Line - Represents a product line in a transfer
 */
class ProductLine
{
    private Product $product;
    private int $quantity;
    private array $metadata;
    
    public function __construct(Product $product, int $quantity, array $metadata = [])
    {
        $this->product = $product;
        $this->quantity = $quantity;
        $this->metadata = $metadata;
    }
    
    public function getProduct(): Product { return $this->product; }
    public function getQuantity(): int { return $this->quantity; }
    public function getMetadata(): array { return $this->metadata; }
}
