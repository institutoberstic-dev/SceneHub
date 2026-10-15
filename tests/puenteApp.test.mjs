import test from 'node:test';
import assert from 'node:assert/strict';
import { comandoVerificacion, siguienteVersion, tipoPorArchivo, versionValida } from '../resources/js/components/puenteApp.js';

test('version format follows motor/__init__.py (1.0.0)', () => {
    assert.equal(versionValida('1.0.0'), true);
    assert.equal(versionValida(' 2.10.3 '), true);
    assert.equal(versionValida('v1.0'), false);
    assert.equal(versionValida('1.0'), false);
});

test('next version is a patch bump of the highest one', () => {
    assert.equal(siguienteVersion([]), '1.0.0');
    assert.equal(siguienteVersion([{ version: '1.0.9' }, { version: '1.0.10' }, { version: 'x' }]), '1.0.11');
    assert.equal(siguienteVersion([{ version: '2.0.0' }, { version: '1.9.9' }]), '2.0.1');
});

test('file type is suggested from the extension', () => {
    assert.equal(tipoPorArchivo('PuenteDatos-1.0.0-instalador.EXE'), 'instalador');
    assert.equal(tipoPorArchivo('PuenteDatos.msi'), 'instalador');
    assert.equal(tipoPorArchivo('PuenteDatos-1.0.0-portable.zip'), 'portable');
    assert.equal(tipoPorArchivo('notas.txt'), '');
});

test('hash command points to the Downloads folder', () => {
    assert.equal(comandoVerificacion('PuenteDatos-1.0.0-instalador.exe'), '(Get-FileHash "$env:USERPROFILE\\Downloads\\PuenteDatos-1.0.0-instalador.exe" -Algorithm SHA256).Hash');
});
