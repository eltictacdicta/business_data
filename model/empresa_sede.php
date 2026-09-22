<?php
/**
 * This file is part of FSFramework originally based on Facturascript 2017
 * Copyright (C) 2025 Javier Trujillo <mistertekcom@gmail.com>
 * Copyright (C) 2013-2020 Carlos Garcia Gomez <neorazorx@gmail.com> (lead developer of Facturascript)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * Una sede de la propia empresa. Es una variante de la cabecera
 * (nombre, direccion, contacto...) que se usa al imprimir documentos de un
 * tipo concreto. Clase sin namespace para compatibilidad con facturacion_base.
 *
 * Fusiona la identidad base de `empresa` con los campos no vacios de la sede
 * mediante `toEmpresa()`, que devuelve un clon transitorio y nunca persiste.
 */
class empresa_sede extends fs_model
{
    private const SQL_SELECT_ALL_FROM = 'SELECT * FROM ';
    private const SQL_WHERE = ' WHERE ';
    private const PK_CODSEDE = 'codsede = ';
    private const CODSEDE_PATTERN = '/^[A-Z0-9]{1,6}$/i';

    /**
     * Campos editables de la sede. Todos tienen un consumidor en la salida
     * PDF; cualquier campo sin consumidor queda fuera de la entidad.
     *
     * @var list<string>
     */
    public const EDITABLE_FIELDS = [
        'descripcion',
        'nombre',
        'cifnif',
        'direccion',
        'apartado',
        'codpostal',
        'ciudad',
        'provincia',
        'codpais',
        'email',
        'web',
        'telefono',
    ];

    /**
     * Vocabulario canonico de tipos de documento y su clave privada de
     * `fs_settings`. El valor almacenado es un `codsede`; vacio o ausente
     * significa "sin sustitucion".
     *
     * @var array<string, string>
     */
    private const TIPOS = [
        'presupuesto' => 'empresa_sede_presupuesto',
        'albaran' => 'empresa_sede_albaran',
        'pedido' => 'empresa_sede_pedido',
        'factura' => 'empresa_sede_factura',
    ];

    public $codsede;
    public $descripcion;
    public $nombre;
    public $cifnif;
    public $direccion;
    public $apartado;
    public $codpostal;
    public $ciudad;
    public $provincia;
    public $codpais;
    public $email;
    public $web;
    public $telefono;

    public function __construct($data = FALSE)
    {
        parent::__construct('empresa_sedes');
        if ($data) {
            $this->hydrate($data);
        } else {
            $this->clear();
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function hydrate(array $data): void
    {
        $this->codsede = $data['codsede'] ?? null;
        $this->descripcion = $data['descripcion'] ?? '';
        $this->nombre = $data['nombre'] ?? '';
        $this->cifnif = $data['cifnif'] ?? '';
        $this->direccion = $data['direccion'] ?? '';
        $this->apartado = $data['apartado'] ?? '';
        $this->codpostal = $data['codpostal'] ?? '';
        $this->ciudad = $data['ciudad'] ?? '';
        $this->provincia = $data['provincia'] ?? '';
        $this->codpais = $data['codpais'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->web = $data['web'] ?? '';
        $this->telefono = $data['telefono'] ?? '';
    }

    protected function install()
    {
        return '';
    }

    public function url()
    {
        if (is_null($this->codsede)) {
            return 'index.php?page=admin_empresa#sedes';
        }

        return 'index.php?page=admin_empresa&codsede=' . $this->codsede . '#sedes';
    }

    public function get($cod)
    {
        $sql = self::SQL_SELECT_ALL_FROM . $this->table_name . self::SQL_WHERE . self::PK_CODSEDE . $this->var2str($cod) . ';';
        $data = $this->db->select($sql);
        if ($data) {
            return new static($data[0]);
        }

        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->codsede)) {
            return FALSE;
        }

        return (bool) $this->db->select(
            self::SQL_SELECT_ALL_FROM . $this->table_name . self::SQL_WHERE . self::PK_CODSEDE . $this->var2str($this->codsede) . ';'
        );
    }

    public function test()
    {
        foreach (self::EDITABLE_FIELDS as $field) {
            $this->{$field} = $this->no_html($this->{$field});
        }

        $codsede = (string) ($this->codsede ?? '');
        if ($codsede === '' || !preg_match(self::CODSEDE_PATTERN, $codsede)) {
            $this->new_error_msg('Código de sede no válido.');
            return FALSE;
        }

        if ((string) $this->nombre === '') {
            $this->new_error_msg('El nombre de la sede es obligatorio.');
            return FALSE;
        }

        return TRUE;
    }

    public function save()
    {
        if (!$this->exists() && ($this->codsede === null || $this->codsede === '')) {
            $this->codsede = $this->get_new_codigo();
        }

        if (!$this->test()) {
            return FALSE;
        }

        if ($this->exists()) {
            $sql = "UPDATE " . $this->table_name . " SET descripcion = " . $this->var2str($this->descripcion) .
                ", nombre = " . $this->var2str($this->nombre) .
                ", cifnif = " . $this->var2str($this->cifnif) .
                ", direccion = " . $this->var2str($this->direccion) .
                ", apartado = " . $this->var2str($this->apartado) .
                ", codpostal = " . $this->var2str($this->codpostal) .
                ", ciudad = " . $this->var2str($this->ciudad) .
                ", provincia = " . $this->var2str($this->provincia) .
                ", codpais = " . $this->var2str($this->codpais) .
                ", email = " . $this->var2str($this->email) .
                ", web = " . $this->var2str($this->web) .
                ", telefono = " . $this->var2str($this->telefono) .
                self::SQL_WHERE . self::PK_CODSEDE . $this->var2str($this->codsede) . ";";
            return $this->db->exec($sql);
        }

        $sql = "INSERT INTO " . $this->table_name . " (codsede,descripcion,nombre,cifnif,direccion,apartado," .
            "codpostal,ciudad,provincia,codpais,email,web,telefono) VALUES (" .
            $this->var2str($this->codsede) . "," .
            $this->var2str($this->descripcion) . "," .
            $this->var2str($this->nombre) . "," .
            $this->var2str($this->cifnif) . "," .
            $this->var2str($this->direccion) . "," .
            $this->var2str($this->apartado) . "," .
            $this->var2str($this->codpostal) . "," .
            $this->var2str($this->ciudad) . "," .
            $this->var2str($this->provincia) . "," .
            $this->var2str($this->codpais) . "," .
            $this->var2str($this->email) . "," .
            $this->var2str($this->web) . "," .
            $this->var2str($this->telefono) . ");";
        return $this->db->exec($sql);
    }

    public function delete()
    {
        $sql = "DELETE FROM " . $this->table_name . self::SQL_WHERE . self::PK_CODSEDE . $this->var2str($this->codsede) . ";";
        return $this->db->exec($sql);
    }

    public function all()
    {
        $sedes = [];
        $sql = self::SQL_SELECT_ALL_FROM . $this->table_name . " ORDER BY descripcion ASC, codsede ASC;";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $row) {
                $sedes[] = new static($row);
            }
        }

        return $sedes;
    }

    public function get_new_codigo(): string
    {
        $sql = 'SELECT MAX(' . $this->db->sql_to_int('codsede') . ') as cod FROM ' . $this->table_name . ';';
        $data = $this->db->select($sql);
        if ($data) {
            return (string) (1 + intval($data[0]['cod']));
        }

        return '1';
    }

    /**
     * Devuelve un clon transitorio de $base con los campos no vacios de la
     * sede superpuestos. Nunca persiste nada y nunca muta $base. El telefono
     * fusionado se publica como el dinamico `telefono1` que lee el PDF.
     */
    public function toEmpresa(\empresa $base): \empresa
    {
        $empresa = clone $base;

        foreach (self::EDITABLE_FIELDS as $field) {
            $value = $this->{$field} ?? '';
            if ($value === '' || !property_exists($empresa, $field)) {
                continue;
            }

            $empresa->{$field} = $value;
        }

        $empresa->telefono1 = $empresa->telefono;

        return $empresa;
    }

    /**
     * Publica el telefono base como `telefono1` sin sustituir un valor ya
     * presente y devolviendo la misma instancia.
     */
    public static function withPrintablePhone(\empresa $empresa): \empresa
    {
        $current = $empresa->telefono1 ?? '';
        if ($current === '') {
            $empresa->telefono1 = $empresa->telefono ?? '';
        }

        return $empresa;
    }

    /**
     * @return array<string, ?string>
     */
    public static function mapping(): array
    {
        $settings = new \fs_settings();
        $mapping = [];

        foreach (self::TIPOS as $tipo => $key) {
            $value = $settings->get($key, '');
            $mapping[$tipo] = ($value === null || $value === '') ? null : (string) $value;
        }

        return $mapping;
    }

    public static function setMappingFor(string $tipo, ?string $codsede, ?callable $sedeLoader = null): bool
    {
        if (!array_key_exists($tipo, self::TIPOS)) {
            return FALSE;
        }

        $code = $codsede ?? '';
        if ($code !== '' && !self::loadSede($code, $sedeLoader) instanceof self) {
            return FALSE;
        }

        $settings = new \fs_settings();
        $settings->set(self::TIPOS[$tipo], $code);

        return $settings->save();
    }

    public static function resolveForDocumentType(string $tipo, \empresa $base, ?callable $sedeLoader = null): ?\empresa
    {
        if (!array_key_exists($tipo, self::TIPOS)) {
            return null;
        }

        $codsede = self::mapping()[$tipo] ?? null;
        if ($codsede === null || $codsede === '') {
            return null;
        }

        $sede = self::loadSede($codsede, $sedeLoader);
        if (!$sede instanceof self) {
            return null;
        }

        return $sede->toEmpresa($base);
    }

    private static function loadSede(string $codsede, ?callable $sedeLoader): ?self
    {
        $loader = $sedeLoader ?? static function (string $cod): ?self {
            $sede = (new self())->get($cod);
            return $sede instanceof self ? $sede : null;
        };

        $sede = $loader($codsede);

        return $sede instanceof self ? $sede : null;
    }

    private function clear()
    {
        $this->codsede = NULL;
        $this->descripcion = '';
        $this->nombre = '';
        $this->cifnif = '';
        $this->direccion = '';
        $this->apartado = '';
        $this->codpostal = '';
        $this->ciudad = '';
        $this->provincia = '';
        $this->codpais = '';
        $this->email = '';
        $this->web = '';
        $this->telefono = '';
    }
}
