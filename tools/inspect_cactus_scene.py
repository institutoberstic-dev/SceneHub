import bpy
from mathutils import Vector

print("CACTUS_SCENE_BEGIN")
print("scene", bpy.context.scene.name)
for obj in bpy.context.scene.objects:
    if obj.type == "MESH":
        world_corners = [obj.matrix_world @ Vector(corner) for corner in obj.bound_box]
        mins = tuple(round(min(v[i] for v in world_corners), 4) for i in range(3))
        maxs = tuple(round(max(v[i] for v in world_corners), 4) for i in range(3))
        dims = tuple(round(v, 4) for v in obj.dimensions)
        print("MESH", repr(obj.name), "verts", len(obj.data.vertices), "dims", dims, "min", mins, "max", maxs, "collection", repr(obj.users_collection[0].name if obj.users_collection else ""))
    else:
        print(obj.type, repr(obj.name))
print("CACTUS_SCENE_END")
