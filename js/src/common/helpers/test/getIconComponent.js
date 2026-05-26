/**
 * Internal dependencies
 */
import { getIconComponent } from '../';

global.ccbEditor = { controls: {} };

describe( 'getIconComponent', () => {
	it.each( [
		[ 'coywolf_custom_blocks', 'CoywolfCustomBlocks' ],
		[ 'account_balance', 'AccountBalance' ],
		[ 'brightness_2', 'Brightness2' ],
		[ 'flight', 'Flight' ],
		[ 'toggle_on', 'ToggleOn' ],
	] )( 'should have the icon component',
		( settingName, expected ) => {
			expect( getIconComponent( settingName ).name ).toEqual( expected );
		}
	);

	it( 'should not have an icon that does not exist', () => {
		expect( getIconComponent( 'does_not_exist' ) ).toEqual( null );
	} );
} );
