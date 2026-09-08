import bpy
from math import radians
from mathutils import Matrix

OUTPUT = r"D:\Backups\Modelos 3D\Cactus\Cactus_hojas_3_cactus.blend"

scene = bpy.context.scene
source_objects = [obj for obj in scene.objects if obj.type == "MESH"]
if not source_objects:
    raise RuntimeError("No mesh objects found in the source scene")

# Keep the original six-part cactus as plant 1 and organize each plant separately.
master = bpy.data.collections.get("Collection")
if master:
    master.name = "Cactus_01"
else:
    master = bpy.data.collections.new("Cactus_01")
    scene.collection.children.link(master)

for index, obj in enumerate(source_objects, start=1):
    obj.name = f"Cactus_01_Pala_{index:02d}"

# Three natural-looking variations: the source plus two linked instances.
variants = [
    ("Cactus_01", (-3.0, 0.15, 0.0), -8.0, 0.96),
    ("Cactus_02", (0.0, -0.10, 0.0), 7.0, 1.06),
    ("Cactus_03", (3.0, 0.25, 0.0), -4.0, 0.88),
]

for plant_name, location, angle_deg, scale in variants:
    transform = (
        Matrix.Translation(location)
        @ Matrix.Rotation(radians(angle_deg), 4, "Z")
        @ Matrix.Scale(scale, 4)
    )

    if plant_name == "Cactus_01":
        for obj in source_objects:
            obj.matrix_world = transform @ obj.matrix_world
        collection = master
        objects = source_objects
    else:
        collection = bpy.data.collections.new(plant_name)
        scene.collection.children.link(collection)
        objects = []
        for index, source in enumerate(source_objects, start=1):
            duplicate = source.copy()
            duplicate.data = source.data  # linked geometry keeps the .blend compact
            duplicate.animation_data_clear()
            duplicate.name = f"{plant_name}_Pala_{index:02d}"
            collection.objects.link(duplicate)
            # Source already includes the first variant transform; remove it before applying this one.
            first_transform = (
                Matrix.Translation(variants[0][1])
                @ Matrix.Rotation(radians(variants[0][2]), 4, "Z")
                @ Matrix.Scale(variants[0][3], 4)
            )
            duplicate.matrix_world = transform @ first_transform.inverted() @ source.matrix_world
            objects.append(duplicate)

    root = bpy.data.objects.new(f"{plant_name}_ROOT", None)
    collection.objects.link(root)
    root.empty_display_type = "CIRCLE"
    root.empty_display_size = 0.35
    root["description"] = "Cactus completo generado a partir del ensamblaje original"

scene["cactus_count"] = 3
scene["generation_note"] = "Tres cactus enlazados con variaciones de escala y rotacion; fuente: Cactus_hojas_1.blend"

bpy.ops.wm.save_as_mainfile(filepath=OUTPUT, compress=True)
print("CACTUS_OUTPUT", OUTPUT)
print("CACTUS_COUNT", scene["cactus_count"])
for collection_name in ("Cactus_01", "Cactus_02", "Cactus_03"):
    col = bpy.data.collections[collection_name]
    meshes = [obj for obj in col.objects if obj.type == "MESH"]
    print(collection_name, "mesh_parts", len(meshes))
