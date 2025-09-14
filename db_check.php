<?php
/**
 * Database Check Script - NewTransferV3
 * Checks for successful database insertions and recent transfer activity
 */

// Include database connection
include 'connection_test.php';

echo "🔍 DATABASE TRANSFER CHECK\n";
echo "==========================\n";

try {
    // Check for today's transfers
    $query = "SELECT transfer_id, outlet_from, outlet_to, transfer_date, transfer_notes, created_at 
              FROM stock_transfers 
              WHERE DATE(created_at) = CURDATE() 
              ORDER BY created_at DESC 
              LIMIT 10";
    
    $result = mysqli_query($connection, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        echo "✅ FOUND TRANSFERS TODAY:\n";
        while ($row = mysqli_fetch_assoc($result)) {
            echo sprintf("🆔 Transfer ID: %s | From: %s | To: %s | Date: %s | Note: %s\n",
                $row['transfer_id'],
                substr($row['outlet_from'], 0, 8) . '...',
                substr($row['outlet_to'], 0, 8) . '...',
                $row['transfer_date'],
                substr($row['transfer_notes'] ?? 'No notes', 0, 30)
            );
        }
    } else {
        echo "❌ NO TRANSFERS FOUND TODAY\n";
        
        // Check last 3 days
        $query2 = "SELECT transfer_id, outlet_from, outlet_to, transfer_date, created_at 
                   FROM stock_transfers 
                   WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                   ORDER BY created_at DESC 
                   LIMIT 5";
        
        $result2 = mysqli_query($connection, $query2);
        if ($result2 && mysqli_num_rows($result2) > 0) {
            echo "📅 RECENT TRANSFERS (LAST 3 DAYS):\n";
            while ($row = mysqli_fetch_assoc($result2)) {
                echo sprintf("🆔 Transfer ID: %s | Created: %s\n",
                    $row['transfer_id'],
                    $row['created_at']
                );
            }
        } else {
            echo "❌ NO RECENT TRANSFERS IN LAST 3 DAYS\n";
        }
    }
    
    // Check specific Transfer ID 13067 (from our earlier successful test)
    $query3 = "SELECT * FROM stock_transfers WHERE transfer_id = 13067";
    $result3 = mysqli_query($connection, $query3);
    
    if ($result3 && mysqli_num_rows($result3) > 0) {
        echo "🎯 TRANSFER 13067 STATUS:\n";
        $transfer = mysqli_fetch_assoc($result3);
        echo sprintf("✅ Found Transfer 13067 | From: %s | To: %s | Date: %s\n",
            substr($transfer['outlet_from'], 0, 8) . '...',
            substr($transfer['outlet_to'], 0, 8) . '...',
            $transfer['created_at']
        );
        
        // Check products for this transfer
        $query4 = "SELECT COUNT(*) as product_count FROM stock_products_to_transfer WHERE transfer_id = 13067";
        $result4 = mysqli_query($connection, $query4);
        if ($result4) {
            $count = mysqli_fetch_assoc($result4)['product_count'];
            echo sprintf("📦 Products in Transfer 13067: %d items\n", $count);
        }
    } else {
        echo "❌ Transfer 13067 NOT FOUND\n";
    }
    
    // Check total transfers count
    $query5 = "SELECT COUNT(*) as total_transfers FROM stock_transfers";
    $result5 = mysqli_query($connection, $query5);
    if ($result5) {
        $total = mysqli_fetch_assoc($result5)['total_transfers'];
        echo sprintf("\n📊 TOTAL TRANSFERS IN DATABASE: %d\n", $total);
    }
    
    echo "\n✅ Database check completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
