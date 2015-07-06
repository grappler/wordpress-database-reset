(function( $ ) {
  'use strict';

  $( '#wp-tables' ).bsmSelect({
    animate: true,
    title: dbReset.selectTable,
    plugins: [$.bsmSelect.plugins.compatibility()]
  });

  $( '#select-all' ).on('click', function(e) {
    e.preventDefault();

    $( '#wp-tables' ).children()
      .attr( 'selected', 'selected' )
      .end()
      .change();
  });

  $( '#wp-tables' ).on( 'change', function() {
    $( '#reactivate' ).toggle( $( "option[value='options']:selected", this ).length > 0 );
  });

  $( '#db-reset-code-confirm' ).on( 'change keyup paste', function() {
    $( '#db-reset-submit' ).prop( 'disabled', $( this ).val() !== $( "#security-code" ).text() );
  });

  $( '#db-reset-submit' ).on('click', function(e) {
    e.preventDefault();

    if ( confirm( dbReset.confirmAlert ) ) {
      $( '#db-reset-form' ).submit();
      $( '#loader' ).show();
    }
  });

})( jQuery );
