<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Teacher;
use App\Models\User;
use Config;
use Illuminate\Contracts\Console\Kernel;
use Spatie\Permission\Models\Role;
use Tests\BaseTenantTest;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class ApprovedTeacherControllerTest.
 *
 * @package Tests\Feature
 */
class ApprovedTeacherControllerTest extends BaseTenantTest
{
    use RefreshDatabase;

    /**
     * Refresh the in-memory database.
     *
     * @return void
     */
    protected function refreshInMemoryDatabase()
    {
        $this->artisan('migrate',[
            '--path' => 'database/migrations/tenant'
        ]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    /** @test */
    public function store_approved_teacher()
    {
        $this->withoutExceptionHandling();
        $manager = factory(User::class)->create();
        $role = Role::firstOrCreate(['name' => 'TeachersManager']);
        Config::set('auth.providers.users.model', User::class);
        $manager->assignRole($role);
        $this->actingAs($manager,'api');

        $response = $this->json('POST','/api/v1/approved_teacher', [
            'username' => 'pepepardo',
            'givenName' => 'Pepe',
            'sn1' => 'Pardo',
            'sn2' => 'Jeans',
            'photo' => 'tenant_test/user_photos/photo.png',

            'code' => '040',
            'administrative_status_id' => 1,
            'specialty_id' => 1,
            'titulacio_acces' => 'Enginyer en Enginyeria',
            'altres_titulacions' => 'Doctorat en Filologia Hispànica',
            'idiomes' => 'Anglès',
            'altres_formacions' => 'Nivell D Català',
            'perfil_professional' => 'Digital, Clil, Projectes FP',
            'data_inici_treball' => '2004',
            'data_incorporacio_centre' => '1993-09-01',
            'data_superacio_oposicions' => '2009',
            'lloc_destinacio_definitiva' => 'Tarragona',

            'identifier_id' => 1,
            'birthdate' => '2008-05-25',
            'birthplace_id' => 1,
            'gender' => 'Home',
            'civil_status' => 'Casat/da',
            'phone' => '977405689',
            'other_phones' => '977405675,977405678',
            'mobile' => '679585427',
            'other_mobiles' => '689585457,679582424',
            'email' => 'pepepardo@jeans.com',
            'other_emails' => 'pepepardo@gmail.com,pepepardo@xtec.cat',
            'notes' => 'Bla bla bla'

        ]);

        $response->assertSuccessful();

        //Check user
        $this->assertNotNull($user = User::findByEmail('pepepardo@iesebre.com'));
        $this->assertEquals('Pepe Pardo Jeans',$user->name);
        $this->assertEquals('pepepardo@iesebre.com',$user->email);
        $this->assertEquals('tenant_test/user_photos/photo.png',$user->photo);
        $this->assertEquals('7e98f5986f80d2cbd012df6bd8801558',$user->photo_hash);

        //Check teacher
        $this->assertNotNull($teacher = Teacher::findByCode('040'));
        $this->assertEquals($user->id,$teacher->user_id);
        $this->assertEquals(1,$teacher->administrative_status_id);
        $this->assertEquals(1,$teacher->specialty_id);
        $this->assertEquals('Enginyer en Enginyeria',$teacher->titulacio_acces);
        $this->assertEquals('Doctorat en Filologia Hispànica',$teacher->altres_titulacions);
        $this->assertEquals('Anglès',$teacher->idiomes);
        $this->assertEquals('Nivell D Català',$teacher->altres_formacions);
        $this->assertEquals('Digital, Clil, Projectes FP',$teacher->perfil_professional);
        $this->assertEquals('2004',$teacher->data_inici_treball);
        $this->assertEquals('1993-09-01',$teacher->data_incorporacio_centre);
        $this->assertEquals('2009',$teacher->data_superacio_oposicions);
        $this->assertEquals('Tarragona',$teacher->lloc_destinacio_definitiva);

        //Check person
        $this->assertNotNull($person = Person::where('user_id',$user->id)->first());
        $this->assertEquals($user->id, $person->user_id);
        $this->assertEquals(1,$person->identifier_id);
        $this->assertEquals('Pepe',$person->givenName);
        $this->assertEquals('Pardo',$person->sn1);
        $this->assertEquals('Jeans',$person->sn2);
        $this->assertEquals('2008-05-25',$person->birthdate);
        $this->assertEquals('1',$person->birthplace_id);
        $this->assertEquals('Home',$person->gender);
        $this->assertEquals('Casat/da',$person->civil_status);
        $this->assertEquals('977405689',$person->phone);
        $this->assertEquals('977405675,977405678',$person->other_phones);
        $this->assertEquals('679585427',$person->mobile);
        $this->assertEquals('689585457,679582424',$person->other_mobiles);
        $this->assertEquals('pepepardo@jeans.com',$person->email);
        $this->assertEquals('pepepardo@gmail.com,pepepardo@xtec.cat',$person->other_emails);
        $this->assertEquals('Bla bla bla',$person->notes);
    }

    /** @test */
    public function regular_user_cannot_store_approved_teacher()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user,'api');

        $response = $this->json('POST','/api/v1/approved_teacher');
        $response->assertStatus(403);
    }
}
