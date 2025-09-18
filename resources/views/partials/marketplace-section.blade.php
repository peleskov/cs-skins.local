<div
    id="marketplace-app"
    data-listings="{{ json_encode($featuredListings) }}"
    data-total="{{ $totalListings }}"
    data-has-more="{{ $hasMorePages ? 'true' : 'false' }}"
    data-seller="{{ $seller ? json_encode($seller) : 'null' }}"
    data-seller-stats="{{ $sellerStats ? json_encode($sellerStats) : 'null' }}"
></div>