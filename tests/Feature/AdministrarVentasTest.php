<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Sale;
use Tests\TestCase;

class AdministrarVentasTest extends TestCase
{
    use RefreshDatabase;

    //Test para verificar que el usuario sea redirigido al login si no hay sesion iniciada.
    public function test_usuario_es_redirigido_al_login_si_no_esta_autenticado()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login')); //Debe redirigir a la página de login.
    }

    //Test para verificar que el usuario tenga acceso al dashboard si ha sido autenticado.
    public function test_usuario_tiene_acceso_al_dashboard_si_esta_autenticado()
    {
        $user = User::factory()->create(); //Crear un usuario.
        $this->actingAs($user); //Iniciar sesión como este usuario.
        
        //Verificar que carga correctamente.
        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
    }

    //Test para verificar que el usuario es capaz de redirigirse al reporte de ventas por medio de la barra de navegación, suponiendo que es usuario administrador.
    public function test_usuario_se_redirige_a_la_seccion_de_administrar_ventas_desde_la_barra_de_navegacion_si_es_administrador()
    {
        //Crear un usuario.
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($user); //Iniciar sesión como este usuario.
        
        //Verificar que el usuario este en el dashboard.
        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        //Verificar que el enlace de "Administrar Ventas" está presente.
        $response->assertSee('Administrar Ventas');
        $response->assertSee(route('sales.index'));

        //Simular el clic en el enlace de "Administrar Ventas".
        $response = $this->get(route('sales.index'));
        $response->assertStatus(200);

        //Verificar que se redirige correctamente a la página de administrar ventas.
        $response->assertSee('Administrar Ventas');
    }

    //Test para verificar que en la vista de ventas no se muestra nada si no hay ventas realizadas.
    public function test_no_muestra_ventas_si_no_hay_ventas_registradas()
    {
        //Crear un usuario.
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($user); //Iniciar sesión como este usuario.

        //Asegurarse de que no hay ventas.
        Sale::truncate(); //Eliminar todas las ventas.

        //Obtener la vista donde se listan las ventas.
        $response = $this->get(route('sales.index'));
        $response->assertStatus(200);

        //Verificar que el mensaje "No hay ventas registradas." se muestra.
        $response->assertSee('No hay ventas registradas.');
    }

    //Test para verificar que las ventas realizadas se cargan correctamente.
    public function test_visualizar_ventas_realizadas()
    {
        //Crear un usuario.
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($user); //Iniciar sesión como este usuario.

        //Crear ventas para el test.
        $sales = Sale::factory()->count(5)->create(); // Asegúrate de tener una fábrica de ventas

        //Obtener la vista donde se listan las ventas.
        $response = $this->get(route('sales.index')); 
        $response->assertStatus(200);

        //Verificar que las ventas estén en la tabla.
        foreach ($sales as $sale) {
            $response->assertSee($sale->id);
            $response->assertSee($sale->seller);
            $response->assertSee($sale->customer);
            $response->assertSee($sale->payment);
            $response->assertSee($sale->total); 
        }

        //Verificar que el botón para generar PDF y eliminar se muestren para un admin.
        $response->assertSee('Eliminar');
    }

    //Test para verificar que de las ventas realizadas se elimina la que se selecciona.
    public function test_eliminar_venta_seleccionada()
    {
        //Crear un usuario.
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($user); //Iniciar sesión como este usuario.

        //Crear una venta para eliminar.
        $sale = Sale::factory()->create();

        //Obtener la vista donde se listan las ventas.
        $response = $this->get(route('sales.index'));

        //Verificar que la venta se muestre en la tabla.
        $response->assertSee($sale->id);

        //Simular la eliminación de la venta.
        $response = $this->delete(route('sales.destroy', $sale->id));
        $response->assertSessionHas('success', 'Venta eliminada con éxito.'); //Verificar mensaje.

        //Verificar que la venta haya sido eliminada.
        $response->assertStatus(302);
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);

        //Verificar que la venta ya no se muestre en la tabla.
        $response->assertRedirect(route('sales.index'));
        $response->assertDontSee($sale->id);
    }

    //Test para verificar que se genera el PDF de la venta que ha sido seleccionada.
    public function test_generar_pdf_de_la_venta_seleccionada()
    {
        //Crear usuario admin.
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user); //Autenticar usuario como admin.

        //Crear una venta para generar el PDF.
        $sale = Sale::factory()->create();

        //Obtener la ruta para generar el PDF.
        $response = $this->get(route('sales.generatePDF', $sale->id));

        //Verificar que la respuesta sea un PDF.
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    //Test para verificar que el usuario puede cerrar la sesión desde la vista de administrar ventas.
    public function test_usuario_puede_cerrar_sesion_desde_la_vista_de_administrar_ventas()
    {
        //Crear usuario admin.
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user); //Autenticar usuario como admin.

        //Acceder a la página de administrar ventas.
        $response = $this->get(route('sales.index'));

        //Verificar que carga correctamente.
        $response->assertStatus(200);

        //Verificar que el nombre del usuario aparece en el botón.
        $response->assertSee($user->name);

        //Simular un clic en el botón del usuario (mostrar el dropdown).
        $response = $this->get(route('sales.index'), [
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest', //Necesario para solicitudes AJAX.
            ]
        ]);

        //Verificar que la opción de "Cerrar Sesión" está presente.
        $response->assertSee('Cerrar Sesión');

        //Simular el clic en "Cerrar Sesión".
        $response = $this->post(route('logout'));

        //Verificar que el usuario fue redirigido a la página de inicio de sesión.
        $response->assertRedirect('/');
        $this->assertGuest(); //Verificar que el usuario ya no está autenticado.
    }
}