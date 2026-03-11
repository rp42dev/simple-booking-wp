<?php
/**
 * Build Script: Generate Free and Pro distributions
 *
 * Usage:
 *   php scripts/build.php free   # Generate Free build
 *   php scripts/build.php pro    # Generate Pro build
 *   php scripts/build.php all    # Generate both
 *
 * Outputs to: builds/
 */

// Configuration
$base_dir = dirname( dirname( __FILE__ ) );
$builds_dir = $base_dir . '/builds';
$temp_dir = $builds_dir . '/.temp';

// Files and directories to exclude from Free version
$free_exclusions = array(
    // Pro payment processing
    'includes/stripe/',
    'includes/webhook/class-stripe-webhook.php',
    'vendor/stripe-php/',
    
    // Pro calendar integrations
    'includes/google/',
    'includes/outlook/',
    'includes/calendar/providers/class-google-provider.php',
    'includes/calendar/providers/class-outlook-provider.php',
    
    // Pro staff management
    'includes/post-types/class-staff.php',
    
    // Build artifacts
    'builds/',
    'scripts/',
    '.git/',
    'node_modules/',
    '.github/',
    'develop/',
);

/**
 * Get version from main plugin file
 */
function get_plugin_version( $base_dir ) {
    $plugin_file = $base_dir . '/simple-booking.php';
    if ( ! file_exists( $plugin_file ) ) {
        return 'unknown';
    }
    
    $content = file_get_contents( $plugin_file );
    if ( preg_match( '/\*\s+Version:\s+(\d+\.\d+\.\d+)/i', $content, $matches ) ) {
        return $matches[1];
    }
    
    return 'unknown';
}

/**
 * Copy directory recursively with exclusions
 */
function copy_dir( $src, $dst, $exclusions = array() ) {
    if ( ! is_dir( $dst ) ) {
        mkdir( $dst, 0755, true );
    }
    
    $dir = opendir( $src );
    if ( ! $dir ) {
        return false;
    }
    
    while ( ( $file = readdir( $dir ) ) !== false ) {
        if ( '.' === $file || '..' === $file ) {
            continue;
        }
        
        $src_path = $src . '/' . $file;
        $dst_path = $dst . '/' . $file;
        $relative_path = str_replace( dirname( dirname( $src ) ), '', $src_path ) . '/' . $file;
        
        // Normalize path separators for comparison
        $relative_path = str_replace( '\\', '/', $relative_path );
        
        $should_exclude = false;
        foreach ( $exclusions as $exclude ) {
            $exclude = str_replace( '\\', '/', $exclude );
            if ( strpos( $relative_path, $exclude ) === 0 || strpos( $relative_path, '/' . $exclude ) !== false ) {
                $should_exclude = true;
                break;
            }
        }
        
        if ( $should_exclude ) {
            continue;
        }
        
        if ( is_dir( $src_path ) ) {
            copy_dir( $src_path, $dst_path, $exclusions );
        } else {
            copy( $src_path, $dst_path );
        }
    }
    
    closedir( $dir );
    return true;
}

/**
 * Remove directory recursively
 */
function remove_dir( $dir ) {
    if ( ! is_dir( $dir ) ) {
        return true;
    }
    
    $files = scandir( $dir );
    foreach ( $files as $file ) {
        if ( '.' === $file || '..' === $file ) {
            continue;
        }
        
        $path = $dir . '/' . $file;
        if ( is_dir( $path ) ) {
            remove_dir( $path );
        } else {
            unlink( $path );
        }
    }
    
    return rmdir( $dir );
}

/**
 * Create distribution zip
 */
function create_zip( $source_dir, $zip_file ) {
    if ( ! extension_loaded( 'zip' ) ) {
        echo "Error: ZIP extension not available\n";
        return false;
    }
    
    $zip = new ZipArchive();
    if ( $zip->open( $zip_file, ZipArchive::CREATE ) !== true ) {
        echo "Error: Cannot create ZIP file\n";
        return false;
    }
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $source_dir )
    );
    
    foreach ( $files as $file ) {
        if ( $file->isDir() ) {
            continue;
        }
        
        $file_path = $file->getRealPath();
        $arc_name = 'simple-booking/' . substr( $file_path, strlen( $source_dir ) + 1 );
        $arc_name = str_replace( '\\', '/', $arc_name );
        
        $zip->addFile( $file_path, $arc_name );
    }
    
    $zip->close();
    return true;
}

// Main execution
$action = isset( $argv[1] ) ? $argv[1] : 'none';

if ( ! in_array( $action, array( 'free', 'pro', 'all' ), true ) ) {
    echo "Usage: php scripts/build.php [free|pro|all]\n";
    exit( 1 );
}

// Create builds directory
if ( ! is_dir( $builds_dir ) ) {
    mkdir( $builds_dir, 0755, true );
}

$version = get_plugin_version( $base_dir );
echo "Building Simple Booking v$version\n\n";

// Build Free version
if ( 'free' === $action || 'all' === $action ) {
    echo "[1/2] Building Free distribution...\n";
    
    // Clean temp
    if ( is_dir( $temp_dir ) ) {
        remove_dir( $temp_dir );
    }
    mkdir( $temp_dir, 0755, true );
    
    // Copy with exclusions
    $temp_plugin_dir = $temp_dir . '/simple-booking';
    copy_dir( $base_dir, $temp_plugin_dir, $free_exclusions );
    
    // Create zip
    $zip_file = $builds_dir . '/simple-booking-free-' . $version . '.zip';
    if ( create_zip( $temp_dir, $zip_file ) ) {
        echo "✓ Free distribution created: " . basename( $zip_file ) . "\n";
    } else {
        echo "✗ Failed to create Free distribution\n";
        exit( 1 );
    }
}

// Build Pro version
if ( 'pro' === $action || 'all' === $action ) {
    echo "[" . ( 'all' === $action ? '2/2' : '1/1' ) . "] Building Pro distribution...\n";
    
    // Clean temp
    if ( is_dir( $temp_dir ) ) {
        remove_dir( $temp_dir );
    }
    mkdir( $temp_dir, 0755, true );
    
    // Copy full codebase
    $temp_plugin_dir = $temp_dir . '/simple-booking';
    copy_dir( $base_dir, $temp_plugin_dir, array( 'builds', 'scripts', '.git', 'node_modules', '.github', 'develop' ) );
    
    // Create zip
    $zip_file = $builds_dir . '/simple-booking-pro-' . $version . '.zip';
    if ( create_zip( $temp_dir, $zip_file ) ) {
        echo "✓ Pro distribution created: " . basename( $zip_file ) . "\n";
    } else {
        echo "✗ Failed to create Pro distribution\n";
        exit( 1 );
    }
}

// Clean up temp
if ( is_dir( $temp_dir ) ) {
    remove_dir( $temp_dir );
}

echo "\n✅ Build complete!\n";
echo "Distributions available in: builds/\n";
