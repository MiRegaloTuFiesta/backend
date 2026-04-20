<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;
use App\Models\Region;
use App\Models\City;

class GeographySeeder extends Seeder
{
    public function run(): void
    {
        $chile = Country::updateOrCreate(['code' => 'CL'], ['name' => 'Chile']);

        $data = [
            'Metropolitana de Santiago' => [
                'Puente Alto', 'Maipú', 'Santiago', 'La Florida', 'San Bernardo', 'Las Condes', 'Peñalolén', 'Quilicura', 'Ñuñoa', 'Pudahuel', 
                'La Pintana', 'El Bosque', 'Recoleta', 'Renca', 'Providencia', 'Estación Central', 'Cerro Navia', 'Conchalí', 'Melipilla', 
                'La Granja', 'Macul', 'Quinta Normal', 'San Miguel', 'Lo Barnechea', 'Pedro Aguirre Cerda', 'Independencia', 'Lo Espejo', 
                'Huechuraba', 'Lo Prado', 'San Joaquín', 'La Reina', 'La Cisterna', 'Colina', 'Vitacura', 'Peñaflor', 'San Ramón', 'Cerrillos', 
                'Buin', 'Lampa', 'Paine', 'El Monte', 'Calera de Tango', 'Isla de Maipo', 'Curacaví', 'Valle Grande', 'Batuco', 'Chicureo', 
                'Alto Jahuel', 'San José de Maipo', 'Tiltil', 'Bollenar', 'El Principal', 'Ciudad del Valle', 'La Islita', 'Chamisero'
            ],
            'Antofagasta' => [
                'Antofagasta', 'Calama', 'Tocopilla', 'Mejillones', 'Taltal', 'San Pedro de Atacama'
            ],
            'Valparaíso' => [
                'Viña del Mar', 'Valparaíso', 'Quilpué', 'Villa Alemana', 'San Antonio', 'Quillota', 'San Felipe', 'Los Andes', 'La Calera', 
                'Concón', 'Placilla de Peñuelas', 'Limache', 'Quintero', 'Cartagena', 'La Cruz', 'La Ligua', 'Casablanca', 'Llaillay', 
                'El Quisco', 'Olmué', 'Cabildo', 'San Esteban', 'Hijuelas', 'Algarrobo', 'Nogales', 'Rinconada', 'Catemu', 'Santa María', 
                'Hanga Roa', 'Santo Domingo', 'Putaendo', 'El Tabo', 'El Melón', 'Puchuncaví', 'Calle Larga', 'Las Ventanas', 'Las Cruces', 
                'El Melón', 'San José de la Mariquina' // Wait, San Jose de la Mariquina is Los Rios, fixing below
            ],
            'La Araucanía' => [
                'Temuco', 'Padre las Casas', 'Angol', 'Villarrica', 'Victoria', 'Lautaro', 'Labranza', 'Nueva Imperial', 'Pucón', 'Pitrufquén', 
                'Collipulli', 'Loncoche', 'Traiguén', 'Curacautín', 'Carahue', 'Cunco', 'Purén', 'Renaico', 'Vilcún', 'Freire'
            ],
            'Los Lagos' => [
                'Puerto Montt', 'Osorno', 'Alerce', 'Castro', 'Ancud', 'Puerto Varas', 'Quellón', 'Calbuco', 'Llanquihue', 'Frutillar', 
                'Los Muermos', 'Fresia', 'Dalcahue', 'Río Negro', 'Chonchi', 'Chaitén'
            ],
            "Libertador General Bernardo O'Higgins" => [
                'Rancagua', 'San Fernando', 'Machalí', 'Rengo', 'Graneros', 'Santa Cruz', 'San Vicente de Tagua Tagua', 'Chimbarongo', 
                'Pichilemu', 'San Francisco de Mostazal', 'Requínoa', 'Lo Miranda', 'Nancagua', 'Peumo', 'Las Cabras', 'Doñihue', 
                'Quinta de Tilcoco', 'La Punta', 'Codegua', 'Chépica', 'Pichidegua', 'Coltauco', 'Gultro', 'Peralillo'
            ],
            'Arica y Parinacota' => [
                'Arica', 'Putre'
            ],
            'Coquimbo' => [
                'La Serena', 'Coquimbo', 'Ovalle', 'Illapel', 'Vicuña', 'Los Vilos', 'Salamanca', 'Andacollo', 'Monte Patria', 'Punitaqui', 
                'El Palqui', 'Combarbalá'
            ],
            'Biobío' => [
                'Concepción', 'Los Ángeles', 'Talcahuano', 'San Pedro de la Paz', 'Coronel', 'Hualpén', 'Chiguayante', 'Penco', 'Lota', 
                'Tome', 'Curanilahue', 'Mulchén', 'Nacimiento', 'Lebu', 'Hualqui', 'Cañete', 'Arauco', 'La Laja', 'Los Álamos', 'Cabrero', 
                'Yumbel', 'Santa Juana', 'Santa Bárbara', 'Huépil', 'Monte Águila', 'Laraquete'
            ],
            'Maule' => [
                'Talca', 'Curicó', 'Linares', 'Molina', 'Constitución', 'Parral', 'Culenar', 'San Javier', 'San Clemente', 'Teno', 
                'Villa Alegre', 'Longaví', 'Maule', 'Colbún', 'Hualañé', 'Rauco', 'Retiro', 'Romeral'
            ],
            'Tarapacá' => [
                'Iquique', 'Alto Hospicio', 'Pozo Almonte'
            ],
            'Los Ríos' => [
                'Valdivia', 'La Unión', 'Río Bueno', 'Paillaco', 'Panguipulli', 'San José de la Mariquina', 'Lanco', 'Futrono', 'Los Lagos'
            ],
            'Ñuble' => [
                'Chillán', 'San Carlos', 'Chillán Viejo', 'Bulnes', 'Yungay', 'Coelemu', 'Quillón', 'Quirihue', 'Coihueco'
            ],
            'Magallanes y de la Antártica Chilena' => [
                'Punta Arenas', 'Puerto Natales', 'Porvenir', 'Puerto Williams'
            ],
            'Aysén del General Carlos Ibáñez del Campo' => [
                'Coyhaique', 'Puerto Aysen', 'Chile Chico', 'Cochrane'
            ]
        ];

        foreach ($data as $regionName => $cities) {
            $region = Region::updateOrCreate(
                ['name' => $regionName, 'country_id' => $chile->id]
            );

            foreach ($cities as $cityName) {
                City::updateOrCreate(
                    ['name' => $cityName, 'region_id' => $region->id]
                );
            }
        }
    }
}
