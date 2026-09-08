import bpy
from math import radians
from mathutils import Matrix, Vector

BASE = r"D:\Backups\Modelos 3D\Cactus\Cactus_hojas_1.blend"
OUTPUT_DIR = r"D:\Backups\Modelos 3D\Cactus"


VARIANTS = [
    {
        "file": "Cactus_01_alto.blend",
        "label": "Alto y estrecho",
        "overall": (0.88, 0.88, 1.12),
        "parts": [
            # dx, dy, dz, rotX, rotY, rotZ, uniform scale
            (0.00, 0.00, 0.00, 0, 0, 0, 1.00),
            (0.15, 0.18, 0.28, -3, 4, -8, 0.92),
            (-0.12, -0.22, 0.38, 4, -3, 7, 0.90),
            (0.12, 0.48, 0.62, -5, 5, -10, 0.82),
            (-0.10, -0.36, 0.72, 4, -4, 8, 0.78),
            (0.06, 0.02, 0.88, -2, 3, -3, 0.88),
        ],
    },
    {
        "file": "Cactus_02_ancho.blend",
        "label": "Ancho y ramificado",
        "overall": (1.14, 1.14, 0.91),
        "parts": [
            (0.00, 0.00, 0.00, 0, 0, 0, 1.08),
            (-0.34, -0.58, -0.20, 6, -6, 18, 1.03),
            (0.40, 0.62, -0.28, -7, 7, -17, 1.06),
            (-0.28, -0.92, -0.42, 8, -4, 23, 0.96),
            (0.34, 0.88, -0.36, -8, 5, -22, 0.92),
            (0.18, 0.12, -0.18, 4, 6, 11, 1.00),
        ],
    },
    {
        "file": "Cactus_03_inclinado.blend",
        "label": "Inclinado y asimetrico",
        "overall": (0.98, 0.98, 1.02),
        "parts": [
            (0.00, 0.00, 0.00, 0, 8, -6, 1.02),
            (0.28, -0.26, 0.10, -8, 12, 16, 0.94),
            (0.52, 0.14, 0.22, 7, 15, -12, 1.08),
            (0.76, -0.38, 0.34, -10, 18, 20, 0.86),
            (0.58, 0.48, 0.06, 9, 13, -18, 0.80),
            (0.92, 0.06, 0.46, -5, 20, 7, 0.92),
        ],
    },
]


def mesh_objects():
    return sorted(
        [obj for obj in bpy.context.scene.objects if obj.type == "MESH"],
        key=lambda obj: obj.name,
    )


for variant_index, variant in enumerate(VARIANTS, start=1):
    if variant_index > 1:
        bpy.ops.wm.open_mainfile(filepath=BASE)

    objects = mesh_objects()
    if len(objects) != 6:
        raise RuntimeError(f"Expected 6 mesh parts, found {len(objects)}")

    collection = objects[0].users_collection[0]
    collection.name = f"Cactus_{variant_index:02d}"

    # Apply an overall proportion change around ground-center.
    overall = Matrix.Diagonal((*variant["overall"], 1.0))
    for part_index, (obj, settings) in enumerate(zip(objects, variant["parts"]), start=1):
        dx, dy, dz, rx, ry, rz, part_scale = settings
        original = obj.matrix_world.copy()
        pivot = original.translation.copy()
        rotation = (
            Matrix.Rotation(radians(rz), 4, "Z")
            @ Matrix.Rotation(radians(ry), 4, "Y")
            @ Matrix.Rotation(radians(rx), 4, "X")
        )
        local_shape = Matrix.Translation(pivot) @ rotation @ Matrix.Scale(part_scale, 4) @ Matrix.Translation(-pivot)
        obj.matrix_world = Matrix.Translation((dx, dy, dz)) @ overall @ local_shape @ original
        obj.name = f"Cactus_{variant_index:02d}_Pala_{part_index:02d}"

    # Re-ground the complete cactus so its lowest point sits at Z=0.
    min_z = min(
        (obj.matrix_world @ Vector(corner)).z
        for obj in objects
        for corner in obj.bound_box
    )
    for obj in objects:
        obj.location.z -= min_z

    scene = bpy.context.scene
    scene["cactus_variant"] = variant_index
    scene["variant_label"] = variant["label"]
    scene["source_file"] = "Cactus_hojas_1.blend"

    output = OUTPUT_DIR + "\\" + variant["file"]
    bpy.ops.wm.save_as_mainfile(filepath=output, compress=True)
    print("SAVED_VARIANT", variant_index, variant["label"], output)

