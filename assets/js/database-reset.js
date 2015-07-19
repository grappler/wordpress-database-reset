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
    showIfValueSelected( $( '#reactivate' ), 'options' );
    showIfValueSelected( $( '#user-disclaimer' ), 'users' );
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

  function showIfValueSelected( element, selectValue ) {
    element.toggle( $( "option[value='" + selectValue + "']:selected", this ).length > 0 );
  }

})( jQuery );
